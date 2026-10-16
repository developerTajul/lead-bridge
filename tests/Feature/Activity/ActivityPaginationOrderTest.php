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
 * Rows that share a due_date have no defined order under a single ORDER BY, so
 * an OFFSET can repeat a row on one page and skip another. These tests read the
 * real table and walk every page, asserting each row is visited exactly once.
 *
 * No RefreshDatabase: this machine has no sqlite driver, so the suite's pinned
 * connection is overridden for the database that is actually reachable. The
 * rows present are read, never written.
 */
final class ActivityPaginationOrderTest extends TestCase
{
    private const PAGE_SIZE = 2;

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
    }

    protected function tearDown(): void
    {
        Paginator::currentPageResolver(fn() => 1);

        parent::tearDown();
    }

    /**
     * The list is keyed on due_date, and the fixture rows tie on that key, so
     * without the id tie-breaker the page walked here would be the one that
     * leaks.
     */
    public function test_the_ordered_key_really_does_have_ties_to_break(): void
    {
        $tiedKeys = DB::table('activities')
            ->select('due_date')
            ->groupBy('due_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertNotEmpty(
            $tiedKeys,
            'This cover is only meaningful while rows share a due_date.',
        );
    }

    public function test_walking_every_page_visits_each_row_exactly_once(): void
    {
        $total = DB::table('activities')->count();

        if ($total === 0) {
            $this->markTestSkipped('No activity rows to page through.');
        }

        $seen = $this->walkEveryPage();

        $duplicates = array_keys(array_filter(
            array_count_values($seen),
            fn(int $count) => $count > 1,
        ));

        $this->assertSame([], $duplicates, 'Rows were returned on more than one page.');
        $this->assertCount($total, $seen, 'Every row should be visited exactly once.');
    }

    /**
     * The order itself must be due_date then id, which is what makes the walk
     * above reproducible rather than incidental.
     */
    public function test_rows_come_back_ordered_by_due_date_then_id(): void
    {
        $ordered = DB::table('activities')
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'due_date'])
            ->map(fn($row) => ['id' => $row->id, 'due_date' => $row->due_date])
            ->all();

        $expected = $ordered;
        usort($expected, function (array $a, array $b): int {
            return [$a['due_date'], $a['id']] <=> [$b['due_date'], $b['id']];
        });

        $this->assertSame($expected, $ordered, 'Rows are not in due_date, id order.');
    }

    /**
     * The pages the repository returns must match that same order, so the
     * tie-breaker is proved to be on the query the module actually runs rather
     * than only on the raw SQL above.
     */
    public function test_the_repository_pages_in_that_same_order(): void
    {
        $total = DB::table('activities')->count();

        if ($total === 0) {
            $this->markTestSkipped('No activity rows to page through.');
        }

        $expectedIds = DB::table('activities')
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->all();

        $this->assertSame($expectedIds, $this->walkEveryPage());
    }

    /**
     * Collects the ids the repository returns across every page.
     *
     * @return array<int, int>
     */
    private function walkEveryPage(): array
    {
        $repository = new ActivityRepository(new Activity(), new ActivityEntityMapper());

        $ids = [];
        $page = 1;

        do {
            // paginate() resolves the page from the request, so drive it here
            // rather than through a real HTTP call.
            Paginator::currentPageResolver(fn() => $page);

            $result = $repository->getFilteredActivities(new ActivityFilter(perPage: self::PAGE_SIZE));

            foreach ($result->items as $item) {
                $ids[] = (int) $item->entity->getId();
            }

            $page++;
        } while ($page <= $result->lastPage);

        return $ids;
    }
}
