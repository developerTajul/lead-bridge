<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\Lead;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature cover for the lead activity timeline endpoint.
 *
 * The route hangs off a lead, so it has to do two things at once: refuse a
 * lead that does not exist, and return the activities attached to a lead that
 * does. The rows here are written through the model rather than inserted as
 * raw arrays, so the morph alias Eloquent actually stores is what the request
 * path is exercised against. Every test cleans up after itself, and the
 * activity table is left as it was found.
 */
final class ApiLeadActivityTimelineTest extends TestCase
{
    use WithoutMiddleware;

    private const ENDPOINT = '/api/v1/leads/%d/activities';

    /**
     * An id well past anything the fixture rows reach, for the not-found case.
     */
    private const MISSING_LEAD_ID = 999999999;

    /**
     * Ids this test wrote, dropped in tearDown so a failure mid-test cannot
     * leave rows behind in the shared database.
     *
     * @var array<int, int>
     */
    private array $seededIds = [];

    /**
     * Ids of the leads this test case created itself, dropped in tearDown
     * alongside their activities.
     *
     * @var array<int, int>
     */
    private array $createdLeadIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml pins sqlite with an in-memory database, which this
        // machine has no driver for. Point the connection at the reachable one.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'lead_bridge_db',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);

        DB::purge('mysql');
    }

    protected function tearDown(): void
    {
        if ($this->seededIds !== []) {
            DB::table('activities')->whereIn('id', $this->seededIds)->delete();
            $this->seededIds = [];
        }

        if ($this->createdLeadIds !== []) {
            // Dropped by id rather than through the relationship, so the
            // cleanup holds even if an activity row was somehow left behind.
            DB::table('activities')->whereIn('trackable_id', $this->createdLeadIds)->delete();
            DB::table('leads')->whereIn('id', $this->createdLeadIds)->delete();
            $this->createdLeadIds = [];
        }

        parent::tearDown();
    }

    public function test_it_returns_the_timeline_of_the_lead(): void
    {
        $lead = $this->makeLead();
        $other = $this->makeLead();

        $this->makeActivity($lead, 'XFINAL-subject-on-target');
        $this->makeActivity($other, 'XFINAL-subject-on-other-lead');

        $response = $this->getJson(sprintf(self::ENDPOINT, $lead->id));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        $subjects = array_column($response->json('data'), 'subject');

        $this->assertContains('XFINAL-subject-on-target', $subjects);
        $this->assertNotContains(
            'XFINAL-subject-on-other-lead',
            $subjects,
            'The timeline must not reach past the lead in the URL.',
        );
    }

    /**
     * The response shape is what a client codes against, so the envelope and
     * the pagination meta are asserted rather than assumed.
     */
    public function test_it_answers_with_the_paginated_envelope(): void
    {
        $lead = $this->makeLead();
        $this->makeActivity($lead, 'XFINAL-envelope');

        $response = $this->getJson(sprintf(self::ENDPOINT, $lead->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [['id', 'trackable_type', 'trackable_id', 'type', 'subject']],
            'meta' => ['total', 'current_page', 'per_page', 'last_page'],
        ]);

        $this->assertSame('lead', $response->json('data.0.trackable_type'));
        $this->assertSame($lead->id, $response->json('data.0.trackable_id'));
    }

    /**
     * A lead with no activities is an empty timeline, not a missing resource.
     *
     * The lead is created by this test rather than discovered among the
     * existing rows, so the empty timeline is a fact the test established
     * instead of one it hoped to find.
     */
    public function test_a_lead_without_activities_returns_an_empty_timeline(): void
    {
        $lead = $this->makeLead();

        $response = $this->getJson(sprintf(self::ENDPOINT, $lead->id));

        $response->assertOk();
        $response->assertJsonPath('data', []);
        $this->assertSame(0, $response->json('meta.total'));
    }

    /**
     * The lead is looked up before anything is returned, so an unknown id is a
     * 404 rather than a 200 carrying an empty list.
     *
     * The id is a fixed one no fixture reaches, so the test reads nothing from
     * the database to decide what to ask for.
     */
    public function test_it_returns_404_for_a_lead_that_does_not_exist(): void
    {
        $missingId = self::MISSING_LEAD_ID;

        $response = $this->getJson(sprintf(self::ENDPOINT, $missingId));

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error_code', 'LEAD_NOT_FOUND');
    }

    /**
     * The timeline is newest-first, which is the whole point of the ordering
     * branch this endpoint selects in the repository.
     */
    public function test_the_timeline_is_returned_newest_first(): void
    {
        $lead = $this->makeLead();

        $older = $this->makeActivity($lead, 'XFINAL-older', '2024-01-01 09:00:00');
        $newer = $this->makeActivity($lead, 'XFINAL-newer', '2024-06-01 09:00:00');

        $response = $this->getJson(sprintf(self::ENDPOINT, $lead->id));

        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertLessThan(
            array_search($older->id, $ids, true),
            array_search($newer->id, $ids, true),
            'The newer activity must come before the older one.',
        );
    }

    public function test_it_pages_the_timeline_and_reports_the_page_size(): void
    {
        // Seeded on a lead this test created empty, so the totals below
        // describe the rows this test wrote instead of whatever already
        // happened to be attached to the lead. Paging is then a property of
        // the request, not of the surrounding data.
        $lead = $this->makeLead();

        foreach (range(1, 3) as $n) {
            $this->makeActivity($lead, sprintf('XFINAL-page-%d', $n));
        }

        $response = $this->getJson(sprintf(self::ENDPOINT, $lead->id) . '?per_page=2');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * An out-of-range or non-numeric page size is client input, so it is
     * refused before the read rather than silently clamped.
     */
    public function test_it_rejects_an_out_of_range_page_size(): void
    {
        $lead = $this->makeLead();

        $response = $this->getJson(sprintf(self::ENDPOINT, $lead->id) . '?per_page=500');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    public function test_it_rejects_a_non_numeric_page_size(): void
    {
        $lead = $this->makeLead();

        $response = $this->getJson(sprintf(self::ENDPOINT, $lead->id) . '?per_page=lots');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    /**
     * The endpoint is only reachable behind the API's auth middleware; the
     * route must not have been registered outside that group.
     */
    public function test_the_route_is_declared_under_the_authenticated_group(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn($r) => str_contains($r->uri(), 'leads/{lead}/activities'));

        $this->assertNotNull($route, 'The timeline route is not registered.');
        $this->assertContains(
            'auth:sanctum',
            $route->gatherMiddleware(),
            'The timeline must sit behind the API auth middleware.',
        );
    }

    /**
     * A fresh lead to hang activities off, created for this test alone.
     *
     * Creating the row rather than reading one keeps the test independent of
     * the shared database: the lead starts with an empty timeline whatever
     * else is stored, and every id below is one this test owns. The email is
     * unique per call because the column is, and the row is dropped in
     * tearDown().
     *
     * @return Lead
     */
    private function makeLead(): Lead
    {
        $lead = new Lead();
        $lead->fill([
            'name' => 'XFinal Timeline Fixture',
            'email' => sprintf('xfinal-timeline-%s@example.test', Str::uuid()),
            'phone' => null,
        ]);
        $lead->save();

        $this->createdLeadIds[] = (int) $lead->id;

        return $lead;
    }

    /**
     * Writes an activity against a lead the way the application does, so the
     * stored morph value is the one Eloquent produces rather than one this
     * test hardcodes.
     *
     * @param Lead        $lead
     * @param string      $subject
     * @param string|null $createdAt
     *
     * @return \App\Models\Activity
     */
    private function makeActivity(Lead $lead, string $subject, ?string $createdAt = null): \App\Models\Activity
    {
        $activity = new \App\Models\Activity();
        $activity->fill([
            'user_id' => null,
            'type' => 'call',
            'subject' => $subject,
            'description' => null,
            'due_date' => null,
            'completed_at' => null,
        ]);
        $activity->trackable()->associate($lead);

        if ($createdAt !== null) {
            $activity->created_at = $createdAt;
            $activity->updated_at = $createdAt;
        }

        $activity->save();

        $this->seededIds[] = (int) $activity->id;

        return $activity;
    }
}
