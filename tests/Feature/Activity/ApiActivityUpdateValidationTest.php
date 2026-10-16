<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

/**
 * Feature cover for the update endpoint's validation rules.
 */
final class ApiActivityUpdateValidationTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindRepository();

        // The transaction manager would open a real connection; these tests
        // only cover the validation rules, so it is stubbed out.
        $this->app->instance(
            TransactionManagerContract::class,
            $this->createMock(TransactionManagerContract::class),
        );
    }

    public function test_reopening_alongside_a_completion_time_is_rejected(): void
    {
        $response = $this->putJson(route('activities.update', 1), [
            'reopen' => true,
            'completed_at' => '2026-10-04 08:15:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('completed_at');
    }

    public function test_completing_with_a_completion_time_is_accepted(): void
    {
        $response = $this->putJson(route('activities.update', 1), [
            'completed_at' => '2026-10-04 08:15:00',
        ]);

        $response->assertOk();
    }

    public function test_reopening_on_its_own_is_accepted(): void
    {
        $response = $this->putJson(route('activities.update', 1), [
            'reopen' => true,
        ]);

        $response->assertOk();
    }

    public function test_a_false_reopen_flag_does_not_conflict_with_a_completion_time(): void
    {
        $response = $this->putJson(route('activities.update', 1), [
            'reopen' => false,
            'completed_at' => '2026-10-04 08:15:00',
        ]);

        $response->assertOk();
    }

    public function test_an_explicit_null_completion_time_does_not_conflict_with_reopening(): void
    {
        $response = $this->putJson(route('activities.update', 1), [
            'reopen' => true,
            'completed_at' => null,
        ]);

        $response->assertOk();
    }

    private function bindRepository(): void
    {
        $repository = new class implements ActivityRepositoryContract {
            public function save(ActivityEntity $activity): ActivityEntity
            {
                return $activity;
            }

            public function paginate(int $perPage = 10): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $perPage, 1);
            }

            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?ActivityProjectionDTO
            {
                return null;
            }

            public function findByIdWithLock(int $id): ?ActivityEntity
            {
                return ActivityEntity::reconstitute(
                    id: $id,
                    trackableType: 'App\\Models\\Lead',
                    trackableId: 42,
                    type: ActivityType::CALL,
                    subject: 'Discovery call',
                    userId: 7,
                );
            }

            public function delete(ActivityEntity $activity): void {}
        };

        $this->app->instance(ActivityRepositoryContract::class, $repository);
    }
}
