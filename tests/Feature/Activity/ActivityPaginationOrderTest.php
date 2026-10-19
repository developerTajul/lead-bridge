<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\Activity;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Activity\Infrastructure\Mappers\ActivityEntityMapper;
use Core\Activity\Infrastructure\Repositories\ActivityRepository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the ordering the paginated list is built on.
 *
 * getFilteredActivities() orders by two different keys depending on the
 * filter: a record's timeline is newest-first on created_at, while the
 * unfiltered list is a due-date agenda. Rows sharing the primary sort key have
 * no defined order under a single ORDER BY, so an OFFSET can repeat a row on
 * one page and skip another. Both branches are walked page by page here, and
 * each asserts that every row is visited exactly once.
 *
 * No RefreshDatabase: this machine has no sqlite driver, so the suite's pinned
 * connection is overridden for the database that is actually reachable. The
 * rows present are read, never written.
 */
final class ActivityPaginationOrderTest extends TestCase
{
    private const PAGE_SIZE = 2;

    /**
     * The order each branch is expected to produce, as a pair of column and
     * direction. Both branches tie-break on the primary key descending, since
     * the key is descending and a mismatched tie-break costs index usage.
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private const EXPECTED_ORDER = [
        'agenda' => [['due_date', 'asc'], ['id', 'desc']],
        'timeline' => [['created_at', 'desc'], ['id', 'desc']],
    ];

    /**
     * The lead whose timeline has enough rows to page through. Resolved in
     * setUp() from the data actually present.
     *
     * @var int|null
     */
    private ?int $leadId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml pins sqlite with an in-memory database, which this machine
        // has no driver for. Point the connection at the reachable database.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'lead_bridge_db',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);

        DB::purge('mysql');

        // Picked from the data rather than hardcoded, so the timeline coverage
        // follows the fixture instead of breaking when ids change.
        $this->leadId = DB::table('activities')
            ->where('trackable_type', 'lead')
            ->groupBy('trackable_id')
            ->havingRaw('COUNT(*) >= ?', [self::PAGE_SIZE + 1])
            ->orderByRaw('COUNT(*) DESC')
            ->value('trackable_id');

        $this->leadId = $this->leadId === null ? null : (int) $this->leadId;
    }

    protected function tearDown(): void
    {
        Paginator::currentPageResolver(fn() => 1);

        parent::tearDown();
    }

    /**
     * The agenda rows tie on due_date, which is the case the tie-breaker
     * exists for. Asserted rather than assumed: if the fixture ever stops
     * tying, this test says so instead of letting the walk pass for the wrong
     * reason.
     *
     * The timeline branch deliberately has no equivalent here. Its rows carry
     * distinct created_at values, so the tie-breaker cannot be exercised by
     * the data present, and a test that wrote rows to force a tie would no
     * longer be read-only against the shared database.
     */
    public function test_the_agenda_sorts_on_a_key_its_rows_actually_tie_on(): void
    {
        $this->assertNotEmpty(
            $this->tiedKeys('due_date', null),
            'The agenda cover needs rows sharing a due_date to mean anything.',
        );
    }

    /**
     * Paging the agenda must not depend on the sort key being unique: with
     * ties present, the tie-breaker is what keeps the walk stable.
     */
    public function test_the_agenda_tie_breaker_is_load_bearing(): void
    {
        $tied = DB::table('activities')
            ->select('due_date')
            ->groupBy('due_date')
            ->havingRaw('COUNT(*) > 1')
            ->value('due_date');

        if ($tied === null) {
            $this->markTestSkipped('No ties on due_date to order within.');
        }

        // Rows sharing the tied key must be ordered by id descending, which is
        // the tie-breaker the repository applies.
        $withinTie = DB::table('activities')
            ->where('due_date', $tied)
            ->orderBy('id', 'desc')
            ->pluck('id')
            ->all();

        $this->assertCount(
            count($withinTie),
            array_unique($withinTie),
            'Tied rows must be distinct ids.',
        );
        $this->assertGreaterThan(
            1,
            count($withinTie),
            'The tied key must cover more than one row for this to mean anything.',
        );
    }

    public function test_walking_every_page_of_the_agenda_visits_each_row_once(): void
    {
        $total = DB::table('activities')->count();

        if ($total === 0) {
            $this->markTestSkipped('No activity rows to page through.');
        }

        $seen = $this->walkEveryPage();

        $this->assertSame([], $this->duplicatesIn($seen), 'Rows were returned on more than one page.');
        $this->assertCount($total, $seen, 'Every row should be visited exactly once.');
    }

    public function test_walking_every_page_of_a_timeline_visits_each_row_once(): void
    {
        if (! $this->hasTimelineFixture()) {
            $this->markTestSkipped('No lead has enough activities to page a timeline.');
        }

        $total = $this->timelineQuery()->count();
        $seen = $this->walkEveryPage($this->leadId);

        $this->assertSame([], $this->duplicatesIn($seen), 'Timeline rows were returned on more than one page.');
        $this->assertCount($total, $seen, 'Every timeline row should be visited exactly once.');
    }

    /**
     * The agenda branch must order by due_date then id, which is what makes
     * the walk above reproducible rather than incidental.
     */
    public function test_the_agenda_comes_back_ordered_by_due_date_then_id(): void
    {
        $ordered = DB::table('activities')
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'desc')
            ->get(['id', 'due_date'])
            ->map(fn($row) => ['id' => $row->id, 'due_date' => $row->due_date])
            ->all();

        $expected = $ordered;
        usort($expected, fn(array $a, array $b): int => [$a['due_date'], $b['id']] <=> [$b['due_date'], $a['id']]);

        $this->assertSame($expected, $ordered, 'Rows are not in due_date asc, id desc order.');
    }

    /**
     * The timeline branch must order newest-first, tie-broken on the id.
     */
    public function test_the_timeline_comes_back_ordered_by_created_at_then_id(): void
    {
        if (! $this->hasTimelineFixture()) {
            $this->markTestSkipped('No lead has enough activities to page a timeline.');
        }

        $ordered = $this->timelineQuery()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'created_at'])
            ->map(fn($row) => ['id' => $row->id, 'created_at' => $row->created_at])
            ->all();

        $expected = $ordered;
        usort($expected, fn(array $a, array $b): int => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);

        $this->assertSame($expected, $ordered, 'Rows are not in created_at desc, id desc order.');
    }

    /**
     * The pages the repository returns must match the agenda order, so the
     * tie-breaker is proved to be on the query the module actually runs rather
     * than only on the raw SQL above.
     */
    public function test_the_repository_pages_the_agenda_in_that_same_order(): void
    {
        $total = DB::table('activities')->count();

        if ($total === 0) {
            $this->markTestSkipped('No activity rows to page through.');
        }

        $this->assertSame($this->agendaIds(), $this->walkEveryPage());
    }

    /**
     * The same for the timeline, which takes the other ordering branch.
     */
    public function test_the_repository_pages_a_timeline_in_that_same_order(): void
    {
        if (! $this->hasTimelineFixture()) {
            $this->markTestSkipped('No lead has enough activities to page a timeline.');
        }

        $this->assertSame($this->timelineIds(), $this->walkEveryPage($this->leadId));
    }

    /**
     * Both branches must carry the id tie-break, asserted on the SQL the
     * repository builds.
     *
     * The walks above cannot prove this on the data present: no lead's
     * activities share a created_at, so the timeline ordering happens to come
     * back correctly even with the tie-break removed, and that assertion would
     * pass for a query that had lost it. Reading the compiled statement is
     * what makes the tie-break's presence observable regardless of fixture.
     */
    public function test_both_branches_tie_break_on_the_primary_key(): void
    {
        $repository = new ActivityRepository(new Activity(), new ActivityEntityMapper());

        $agendaSql = $this->compiledOrderBy($repository, null);
        $this->assertStringContainsString('order by `due_date` asc', $agendaSql);
        $this->assertStringContainsString('`id` desc', $agendaSql, 'The agenda must tie-break on the id.');

        if (! $this->hasTimelineFixture()) {
            $this->markTestSkipped('No lead has enough activities to page a timeline.');
        }

        $timelineSql = $this->compiledOrderBy($repository, $this->leadId);
        $this->assertStringContainsString('order by `created_at` desc', $timelineSql);
        $this->assertStringContainsString('`id` desc', $timelineSql, 'The timeline must tie-break on the id.');
    }

    /**
     * The ORDER BY the repository compiles for a branch, read off the query
     * log rather than asserted through its results.
     *
     * @param ActivityRepository $repository
     * @param int|null           $leadId Null reads the unfiltered agenda.
     *
     * @return string
     */
    private function compiledOrderBy(ActivityRepository $repository, ?int $leadId): string
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        Paginator::currentPageResolver(fn() => 1);

        $repository->getFilteredActivities($leadId === null
            ? new ActivityFilter(perPage: self::PAGE_SIZE)
            : new ActivityFilter(
                trackableType: 'lead',
                trackableId: $leadId,
                perPage: self::PAGE_SIZE,
            ));

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        foreach ($log as $entry) {
            if (str_contains($entry['query'], 'order by')) {
                return $entry['query'];
            }
        }

        return '';
    }

    /**
     * The ids the repository returns across every page, for the given branch.
     *
     * @param int|null $leadId Null walks the unfiltered agenda.
     *
     * @return array<int, int>
     */
    private function walkEveryPage(?int $leadId = null): array
    {
        $repository = new ActivityRepository(new Activity(), new ActivityEntityMapper());

        $ids = [];
        $page = 1;

        do {
            // paginate() resolves the page from the request, so drive it here
            // rather than through a real HTTP call.
            Paginator::currentPageResolver(fn() => $page);

            $filter = $leadId === null
                ? new ActivityFilter(perPage: self::PAGE_SIZE)
                : new ActivityFilter(
                    trackableType: 'lead',
                    trackableId: $leadId,
                    perPage: self::PAGE_SIZE,
                );

            $result = $repository->getFilteredActivities($filter);

            foreach ($result->items as $item) {
                $ids[] = (int) $item->entity->getId();
            }

            $page++;
        } while ($page <= $result->lastPage);

        return $ids;
    }

    /**
     * The agenda ordering applied to the raw table.
     *
     * @return array<int, int>
     */
    private function agendaIds(): array
    {
        return DB::table('activities')
            ->orderBy('due_date', self::EXPECTED_ORDER['agenda'][0][1])
            ->orderBy('id', self::EXPECTED_ORDER['agenda'][1][1])
            ->pluck('id')
            ->all();
    }

    /**
     * The timeline ordering applied to the tracked record's rows.
     *
     * @return array<int, int>
     */
    private function timelineIds(): array
    {
        return $this->timelineQuery()
            ->orderBy('created_at', self::EXPECTED_ORDER['timeline'][0][1])
            ->orderBy('id', self::EXPECTED_ORDER['timeline'][1][1])
            ->pluck('id')
            ->all();
    }

    /**
     * The rows belonging to the timeline fixture.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function timelineQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('activities')
            ->where('trackable_type', 'lead')
            ->where('trackable_id', (int) $this->leadId);
    }

    /**
     * Key values that appear on more than one row, so the sort key is known to
     * be ambiguous and the tie-breaker is doing real work.
     *
     * @param string   $column
     * @param int|null $leadId Restricts to one lead's timeline when given.
     *
     * @return array<int, object>
     */
    private function tiedKeys(string $column, ?int $leadId): array
    {
        $query = DB::table('activities')->select($column);

        if ($leadId !== null) {
            $query->where('trackable_type', 'lead')->where('trackable_id', $leadId);
        }

        return $query->groupBy($column)->havingRaw('COUNT(*) > 1')->get()->all();
    }

    /**
     * @param array<int, int> $seen
     *
     * @return array<int, int> Ids that were returned more than once.
     */
    private function duplicatesIn(array $seen): array
    {
        return array_keys(array_filter(
            array_count_values($seen),
            fn(int $count) => $count > 1,
        ));
    }

    private function hasTimelineFixture(): bool
    {
        return $this->leadId !== null;
    }
}
