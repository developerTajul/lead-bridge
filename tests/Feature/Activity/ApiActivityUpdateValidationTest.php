<?php

declare(strict_types=1);

namespace Tests\Feature\Activity;

use Core\Activity\Application\Contracts\ActivityQueryContract;
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

        // The stub row is completed, so a completion is a domain refusal (422),
        // not a bad payload. What matters here is that the payload passed
        // validation and reached the domain.
        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'ACTIVITY_INVALID');
        $response->assertJsonPath('message', 'The activity is already completed.');
        $response->assertJsonMissingValidationErrors('completed_at');
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

        // A benign false flag must not trip the reopen/completion conflict, so
        // the completion is judged on its own. The stub row is completed, so
        // that judgement is a domain refusal rather than a validation error.
        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'ACTIVITY_INVALID');
        $response->assertJsonPath('message', 'The activity is already completed.');
        $response->assertJsonMissingValidationErrors('completed_at');
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
        // The write path now goes through the domain repository port, and reads
        // the saved row back through the query port to build the response.
        $entity = $this->makeEntity(1);

        $this->app->instance(ActivityQueryContract::class, new class($entity) implements ActivityQueryContract {
            public function __construct(private readonly ActivityEntity $entity) {}

            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?ActivityProjectionDTO
            {
                return new ActivityProjectionDTO(entity: $this->entity);
            }
        });

        $this->app->instance(ActivityRepositoryContract::class, new class($entity) implements ActivityRepositoryContract {
            public function __construct(private readonly ActivityEntity $entity) {}

            public function save(ActivityEntity $activity): ActivityEntity
            {
                return $activity;
            }

            public function findByIdWithLock(int $id): ?ActivityEntity
            {
                return $this->entity;
            }

            public function delete(ActivityEntity $activity): void {}

            public function clearLockedRecord(int $id): void {}
        });
    }

    private function makeEntity(int $id): ActivityEntity
    {
        return ActivityEntity::reconstitute(
            id: $id,
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            // The row a reopen payload reads back under the lock is a completed
            // one, since that is the only state a reopen has to change.
            completedAt: new \DateTimeImmutable('2026-10-02 11:00:00'),
        );
    }
}
