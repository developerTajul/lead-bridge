<?php

declare(strict_types=1);

namespace Tests\Feature\Lead;

use App\Models\User;
use Core\Lead\Application\Contracts\LeadQueryContract;
use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\LeadRules;
use Core\Lead\Domain\ValueObjects\LeadFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Feature cover for the admin lead list route.
 */
final class AdminLeadIndexTest extends TestCase
{
    use WithoutMiddleware;

    /** @var array<int, LeadFilter> */
    private array $receivedFilters = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindRecordingRepository();
    }

    public function test_the_route_resolves_and_renders(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index'));

        $response->assertOk();
        $response->assertViewIs('admin.leads.index');
        $response->assertViewHas('leads');
        $response->assertViewHas('pagination');
        $response->assertViewHas('statusOptions');
    }

    public function test_index_defaults_the_filter_when_nothing_is_supplied(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.leads.index'));

        $this->assertCount(1, $this->receivedFilters);
        $this->assertNull($this->receivedFilters[0]->status);
        $this->assertNull($this->receivedFilters[0]->search);
    }

    public function test_index_maps_the_query_string_onto_the_filter(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.leads.index', [
            'status' => 'qualified',
            'source' => 'referral',
            'search' => 'acme',
            'per_page' => 25,
        ]));

        $filter = $this->receivedFilters[0];

        $this->assertSame(LeadStatus::QUALIFIED, $filter->status);
        $this->assertSame('referral', $filter->source);
        $this->assertSame('acme', $filter->search);
        $this->assertSame(25, $filter->perPage);
    }

    public function test_index_caps_the_page_size(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index', ['per_page' => 5000]));

        $this->assertSame(LeadRules::MAX_PER_PAGE, $this->receivedFilters[0]->perPage);
    }

    public function test_index_falls_back_to_an_unfiltered_list_for_a_bogus_filter(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index', ['status' => 'bogus']));

        $response->assertOk();
        $this->assertNull($this->receivedFilters[0]->status);
        $response->assertSee('Invalid filter applied. Showing unfiltered results.');
    }

    /**
     * Regression guard: the view reads snake_case response properties, so a
     * non-empty page must render without an ErrorException.
     */
    public function test_index_renders_a_non_empty_page(): void
    {
        $this->bindRecordingRepository([$this->makeLead(id: 1, name: 'Acme Prospect')]);

        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index'));

        $response->assertOk();
        $response->assertSee('Acme Prospect');
        $response->assertSee('Acme Ltd');
        $response->assertSee('prospect@acme.test');
        // The status is shown by its label, not the raw enum value.
        $response->assertSee('Qualified');
        // The stored ISO string is reformatted for the table.
        $response->assertSee('Oct 05, 2026 10:13 AM');
        $response->assertDontSee('2026-10-05T10:13:16+00:00');
    }

    public function test_index_shows_the_empty_state_when_there_are_no_leads(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index'));

        $response->assertOk();
        $response->assertSee('No leads found.');
    }

    public function test_index_hides_pagination_when_there_are_no_leads(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index'));

        $response->assertOk();
        $response->assertDontSee('Showing page');
    }

    public function test_index_renders_the_pagination_bar_with_a_total(): void
    {
        $this->bindRecordingRepository([$this->makeLead(id: 1, name: 'Acme Prospect')], total: 4, lastPage: 2);

        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index'));

        $response->assertOk();
        $response->assertSee('Showing page 1 of 2');
    }

    /**
     * Pagination must not drop the active criteria, or paging a filtered list
     * silently widens it.
     */
    public function test_pagination_links_preserve_the_active_filters(): void
    {
        $this->bindRecordingRepository([$this->makeLead(id: 1, name: 'Acme Prospect')], total: 4, lastPage: 2);

        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index', [
                'status' => 'qualified',
                'search' => 'acme',
                'per_page' => 10,
            ]));

        $response->assertOk();
        $response->assertSee('status=qualified', false);
        $response->assertSee('search=acme', false);
        $response->assertSee('page=2', false);
    }

    public function test_index_reports_a_read_failure_without_crashing(): void
    {
        $this->app->instance(LeadQueryContract::class, new class implements LeadQueryContract {
            public function getFilteredLeads(LeadFilter $filter): PaginatedResult
            {
                throw new \RuntimeException('connection lost');
            }

            public function findById(int $id): ?LeadProjectionDTO
            {
                return null;
            }
        });

        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.leads.index'));

        $response->assertOk();
        $response->assertSee('Failed to retrieve leads.');
    }

    private function bindRecordingRepository(
        array $items = [],
        ?int $total = null,
        int $lastPage = 1,
    ): void {
        $repository = new class($items, $total ?? count($items), $lastPage, $this) implements LeadQueryContract {
            /**
             * @param array<int, LeadEntity> $items
             */
            public function __construct(
                private array $items,
                private int $total,
                private int $lastPage,
                private AdminLeadIndexTest $test,
            ) {}

            public function getFilteredLeads(LeadFilter $filter): PaginatedResult
            {
                $this->test->recordFilter($filter);

                $projections = array_map(
                    fn(LeadEntity $entity) => new LeadProjectionDTO(
                        entity: $entity,
                        createdAt: new \DateTimeImmutable('2026-10-05 10:13:16'),
                        updatedAt: new \DateTimeImmutable('2026-10-05 10:13:16'),
                    ),
                    $this->items,
                );

                return new PaginatedResult(
                    items: $projections,
                    total: $this->total,
                    currentPage: 1,
                    perPage: $filter->perPage,
                    lastPage: $this->lastPage,
                );
            }

            public function findById(int $id): ?LeadProjectionDTO
            {
                return null;
            }
        };

        $this->app->instance(LeadQueryContract::class, $repository);
    }

    /**
     * Called by the bound repository stub so the test can inspect the criteria.
     */
    public function recordFilter(LeadFilter $filter): void
    {
        $this->receivedFilters[] = $filter;
    }

    private function makeUser(): User
    {
        return new User(['name' => 'Tajul', 'email' => 'tajul@example.com']);
    }

    private function makeLead(int $id, string $name): LeadEntity
    {
        return new LeadEntity(
            id: $id,
            name: $name,
            email: 'prospect@acme.test',
            phone: '555-0100',
            companyName: 'Acme Ltd',
            status: LeadStatus::QUALIFIED,
            source: 'referral',
            createdAt: '2026-10-05 10:13:16',
            updatedAt: '2026-10-05 10:13:16',
        );
    }
}
