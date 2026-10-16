<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * A lock read caches the row so the save() that follows writes back to it. That
 * cache entry must not outlive the operation: the repository is transient, so a
 * row left behind keeps its model alive and lets a later save() for that ID
 * write back a row read before a rollback.
 */
final class ActivityLockedRecordCleanupTest extends TestCase
{
    /** @var array<int, int> */
    private array $clearedIds = [];

    /** @var array<int, int> */
    private array $savedIds = [];

    /** @var array<int, int> */
    private array $lockReadIds = [];

    public function test_a_successful_delete_leaves_the_service_with_nothing_to_clean_up(): void
    {
        // delete() drops the row itself, so the success path issues no second
        // cleanup. The lock read still ran, which is what put a row in the cache.
        $result = $this->makeService()->deleteActivity(1);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([1], $this->lockReadIds);
        $this->assertSame([], $this->clearedIds);
    }

    public function test_a_delete_that_fails_after_the_lock_read_clears_the_record(): void
    {
        // delete() still drops the row in its own finally, and the service drops
        // it again on the way out. Both are correct: the cleanup is idempotent,
        // and the service's call is what covers a repository whose delete never
        // ran at all.
        $this->makeService(deleteThrows: true)->deleteActivity(5);

        $this->assertSame([5], $this->lockReadIds);
        $this->assertSame([5], $this->clearedIds);
    }

    public function test_a_rejected_update_clears_the_record_it_locked(): void
    {
        // The payload both completes and reopens, which applyChanges() refuses
        // before mutating anything, so save() is never reached and nothing
        // consumed the cached row. The failure path has to drop it.
        $result = $this->makeService()->update(new ActivityUpdateDTO(
            id: 3,
            completedAt: new \DateTimeImmutable('2026-10-02 11:00:00'),
            reopen: true,
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_INVALID', $result->errorCode);
        $this->assertSame([3], $this->clearedIds);
        $this->assertSame([], $this->savedIds, 'The write must not have reached save().');
    }

    public function test_a_failed_commit_on_update_clears_the_record_it_locked(): void
    {
        $result = $this->makeService(commitThrows: true)
            ->update(new ActivityUpdateDTO(id: 9, subject: 'Updated subject'));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_UPDATE_FAILED', $result->errorCode);
        $this->assertSame([9], $this->clearedIds);
    }

    public function test_a_failed_delete_clears_the_record_it_locked(): void
    {
        $result = $this->makeService(deleteThrows: true)->deleteActivity(11);

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_DELETE_FAILED', $result->errorCode);
        $this->assertSame([11], $this->lockReadIds);
        $this->assertSame([11], $this->clearedIds);
    }

    public function test_a_successful_update_does_not_clear_a_record_the_save_already_consumed(): void
    {
        // save() consumes the entry, so the success path must not issue a
        // second cleanup for it.
        $result = $this->makeService()->update(new ActivityUpdateDTO(id: 4, subject: 'Updated subject'));

        $this->assertTrue($result->isSuccess());
        $this->assertSame([4], $this->savedIds);
        $this->assertSame([], $this->clearedIds);
    }

    /**
     * @param bool $commitThrows Make commit() fail, as a deadlock would.
     * @param bool $deleteThrows Make the delete fail, as a missing row would.
     */
    private function makeService(bool $commitThrows = false, bool $deleteThrows = false): ActivityService
    {
        $clearedIds = &$this->clearedIds;
        $savedIds = &$this->savedIds;
        $lockReadIds = &$this->lockReadIds;

        $repository = new class($deleteThrows, $clearedIds, $savedIds, $lockReadIds) implements ActivityRepositoryContract, ActivityQueryContract {
            /**
             * @param array<int, int> $clearedIds
             * @param array<int, int> $savedIds
             * @param array<int, int> $lockReadIds
             */
            public function __construct(
                private readonly bool $deleteThrows,
                private array &$clearedIds,
                private array &$savedIds,
                private array &$lockReadIds,
            ) {}

            public function save(ActivityEntity $activity): ActivityEntity
            {
                // The real repository drops the cached entry here, when it takes
                // the locked row to write back to.
                $this->savedIds[] = (int) $activity->getId();

                return $this->hydrated((int) $activity->getId());
            }

            public function findByIdWithLock(int $id): ?ActivityEntity
            {
                // Assertions live here, not in save(): a payload the domain
                // rejects never reaches save(), and a row only leaves the cache
                // once save() takes it, so "no save" and "still cached" are the
                // same condition. Asserting on save() would pass for a payload
                // rejected before the lock read as well as after it.
                $this->lockReadIds[] = $id;

                return $this->hydrated($id);
            }

            public function delete(ActivityEntity $activity): void
            {
                if ($this->deleteThrows) {
                    throw new RuntimeException('Delete failed.');
                }
            }

            public function clearLockedRecord(int $id): void
            {
                $this->clearedIds[] = $id;
            }

            public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
            {
                return new PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?\Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO
            {
                return new \Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO(
                    entity: $this->hydrated($id),
                );
            }

            private function hydrated(int $id): ActivityEntity
            {
                // Completed, so a reopen has to clear the completion and the
                // domain guard on an empty subject is what rejects it.
                return ActivityEntity::reconstitute(
                    id: $id,
                    trackableType: 'lead',
                    trackableId: 42,
                    type: ActivityType::CALL,
                    subject: 'Discovery call',
                    userId: 7,
                    completedAt: new \DateTimeImmutable('2026-10-02 11:00:00'),
                );
            }
        };

        $transactionManager = new class($commitThrows) implements TransactionManagerContract {
            public function __construct(private readonly bool $commitThrows) {}

            public function beginTransaction(): void {}

            public function commit(): void
            {
                if ($this->commitThrows) {
                    throw new RuntimeException('Commit failed.');
                }
            }

            public function rollback(): void {}

            public function run(callable $callback): mixed
            {
                return $callback();
            }
        };

        return new ActivityService(
            activityRepository: $repository,
            activityQuery: $repository,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $transactionManager,
            logger: $this->createMock(LoggerInterface::class),
            eventDispatcher: $this->createMock(EventDispatcherContract::class),
        );
    }
}
