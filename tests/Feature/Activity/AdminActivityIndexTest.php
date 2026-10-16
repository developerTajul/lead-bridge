<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use App\Models\User;
use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Feature cover for the admin activity list route.
 */
final class AdminActivityIndexTest extends TestCase
{
    use WithoutMiddleware;

    /** @var array<int, ActivityFilter> */
    private array $receivedFilters = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindRecordingRepository();
    }

    public function test_the_route_resolves_and_renders(): void
    {
        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.index'));

        $response->assertOk();
        $response->assertViewIs('admin.activities.index');
        $response->assertViewHas('activities');
        $response->assertViewHas('pagination');
    }

    public function test_index_builds_a_filter_without_a_type_error(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.activities.index'));

        $this->assertCount(1, $this->receivedFilters);
        $this->assertSame(10, $this->receivedFilters[0]->perPage);
    }

    public function test_index_maps_the_query_string_onto_the_filter(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.activities.index', [
            'status' => 'pending',
            'type' => 'call',
            'user_id' => 7,
            'filter' => 'today',
            'per_page' => 25,
        ]));

        $filter = $this->receivedFilters[0];

        $this->assertSame('pending', $filter->status);
        $this->assertSame('call', $filter->type);
        $this->assertSame(7, $filter->userId);
        $this->assertSame('today', $filter->period);
        $this->assertSame(25, $filter->perPage);
    }

    public function test_index_defaults_the_filter_when_nothing_is_supplied(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.activities.index'));

        $filter = $this->receivedFilters[0];

        $this->assertNull($filter->status);
        $this->assertNull($filter->type);
        $this->assertNull($filter->userId);
        $this->assertNull($filter->period);
        $this->assertSame(10, $filter->perPage);
    }

    public function test_index_caps_the_page_size(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.activities.index', ['per_page' => 5000]));

        $this->assertSame(100, $this->receivedFilters[0]->perPage);
    }

    /**
     * Regression: the view read camelCase properties (trackableType, dueDate,
     * completedAt) while ActivityResponseDTO declares them snake_case, so any
     * non-empty page raised an ErrorException and returned 500.
     */
    public function test_index_renders_a_non_empty_page(): void
    {
        $this->bindRecordingRepository([$this->makeEntity()]);

        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.index'));

        $response->assertOk();
        $response->assertSee('Discovery call');
        $response->assertSee('Lead #42');
        $response->assertSee('2026-10-05 09:30:00');
        $response->assertSee('Open');
        $this->assertCount(1, $this->receivedFilters);
    }

    public function test_index_renders_a_completed_activity_as_completed(): void
    {
        $entity = ActivityEntity::reconstitute(
            id: 2,
            trackableType: 'App\\Models\\Lead',
            trackableId: 43,
            type: ActivityType::MEETING,
            subject: 'Kickoff meeting',
            userId: 7,
            completedAt: new \DateTimeImmutable('2026-10-02 11:00:00'),
        );

        $this->bindRecordingRepository([$entity]);

        $response = $this->actingAs($this->makeUser())
            ->get(route('admin.activities.index'));

        $response->assertOk();
        $response->assertSee('Kickoff meeting');
        $response->assertSee('Completed');
    }

    private function bindRecordingRepository(array $items = []): void
    {
        $repository = new class($items, $this) implements ActivityQueryContract {
            /**
             * @param array<int, ActivityEntity> $items
             */
            public function __construct(
                private array $items,
                private AdminActivityIndexTest $test,
            ) {}

            public function paginate(int $perPage = 10): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $perPage, 1);
            }

            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                $this->test->recordFilter($filter);

                $projections = array_map(
                    fn(ActivityEntity $entity) => new ActivityProjectionDTO(entity: $entity),
                    $this->items,
                );

                return new PaginatedResult(
                    items: $projections,
                    total: count($projections),
                    currentPage: 1,
                    perPage: $filter->perPage,
                    lastPage: 1,
                );
            }

            public function findById(int $id): ?ActivityProjectionDTO
            {
                return null;
            }
        };

        $this->app->instance(ActivityQueryContract::class, $repository);
    }

    /**
     * Called by the bound repository stub so the test can inspect the criteria.
     */
    public function recordFilter(ActivityFilter $filter): void
    {
        $this->receivedFilters[] = $filter;
    }

    private function makeUser(): User
    {
        return new User(['name' => 'Tajul', 'email' => 'tajul@example.com']);
    }

    private function makeEntity(): ActivityEntity
    {
        return ActivityEntity::reconstitute(
            id: 1,
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: new \DateTimeImmutable('2026-10-05 09:30:00'),
        );
    }
}
