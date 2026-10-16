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
use Core\Activity\Domain\Events\ActivityCreatedEvent;
use Core\Activity\Domain\Events\ActivityDeletedEvent;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Covers what the write paths release and dispatch. save() hands back a freshly
 * hydrated entity, so an entity that records a change has to be drained before
 * that return value replaces it.
 */
final class ActivityEventLossTest extends TestCase
{
    /** @var array<int, object> */
    private array $dispatched = [];

    private bool $rolledBack = false;

    private ?ActivityEntity $entityAtCommit = null;

    public function test_update_dispatches_whatever_the_mutated_entity_recorded(): void
    {
        // The probe records on the entity the service actually mutates, so the
        // events only survive if the service drains it before overwriting it
        // with save()'s return value.
        $service = $this->makeService(recordOnSave: true);

        $result = $service->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject'));

        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(ActivityCreatedEvent::class, $this->dispatched[0]);
    }

    public function test_update_dispatches_an_event_recorded_during_the_mutation(): void
    {
        // Mirrors the real sequence: applyChanges() records on the entity under
        // the lock, then save() replaces the variable holding it.
        $service = $this->makeService(recordOnLock: true);

        $service->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject', reopen: true));

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(ActivityDeletedEvent::class, $this->dispatched[0]);
    }

    public function test_a_create_dispatches_exactly_one_created_event(): void
    {
        $this->makeService()->create($this->makeCreateDTO());

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(ActivityCreatedEvent::class, $this->dispatched[0]);
    }

    public function test_the_created_event_carries_the_saved_id_not_a_null_cast(): void
    {
        $this->makeService()->create($this->makeCreateDTO());

        /** @var ActivityCreatedEvent $event */
        $event = $this->dispatched[0];

        $this->assertSame(7, $event->activityId);
    }

    public function test_a_delete_dispatches_exactly_one_deleted_event(): void
    {
        $this->makeService()->deleteActivity(1);

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(ActivityDeletedEvent::class, $this->dispatched[0]);
    }

    public function test_a_failed_commit_does_not_dispatch_the_deleted_event(): void
    {
        $service = $this->makeService(commitThrows: true);

        $result = $service->deleteActivity(1);

        // The delete never landed, so listeners must not hear about it.
        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_DELETE_FAILED', $result->errorCode);
        $this->assertSame([], $this->dispatched);
    }

    /**
     * The delete event is drained before the commit, so a commit that never
     * resolves cannot strand it. Releasing after commit() would still dispatch
     * nothing here, but it would leave the event on an entity that the caller
     * has already been told failed.
     */
    public function test_the_deleted_event_is_drained_before_the_commit(): void
    {
        $service = $this->makeService(commitThrows: true);

        $service->deleteActivity(1);

        $this->assertNotNull($this->entityAtCommit, 'The lock read never ran.');
        $this->assertSame(
            [],
            $this->entityAtCommit->releaseEvents(),
            'The entity must be drained before commit() is called.',
        );
    }

    public function test_a_failed_commit_rolls_the_transaction_back(): void
    {
        $service = $this->makeService(commitThrows: true, trackingRollback: true);

        $service->deleteActivity(1);

        $this->assertTrue($this->rolledBack);
    }

    public function test_recording_a_created_event_on_an_unsaved_entity_is_refused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot record event for an entity without an ID.');

        // A null ID would otherwise be cast to 0 and dispatched as a real ID.
        $this->makeUnsavedEntity()->recordCreated();
    }

    public function test_recording_a_deleted_event_on_an_unsaved_entity_is_refused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot record event for an entity without an ID.');

        $this->makeUnsavedEntity()->recordDeleted();
    }

    /**
     * @param bool $recordOnSave      Record on the entity handed to save().
     * @param bool $recordOnLock      Record on the entity returned by the lock read.
     * @param bool $commitThrows      Make commit() fail, as a deadlock would.
     * @param bool $trackingRollback  Record whether rollback() was reached.
     */
    private function makeService(
        bool $recordOnSave = false,
        bool $recordOnLock = false,
        bool $commitThrows = false,
        bool $trackingRollback = false,
    ): ActivityService {
        $entityAtCommit = &$this->entityAtCommit;

        $repository = new class($recordOnSave, $recordOnLock, $entityAtCommit) implements ActivityRepositoryContract, ActivityQueryContract {
            /** @param ?ActivityEntity $entityAtCommit */
            public function __construct(
                private readonly bool $recordOnSave,
                private readonly bool $recordOnLock,
                private ?ActivityEntity &$entityAtCommit,
            ) {}

            public function save(ActivityEntity $activity): ActivityEntity
            {
                if ($this->recordOnSave) {
                    $activity->recordCreated();
                }

                // Mirrors the real repository: a new instance, never the input.
                return $this->hydrated();
            }

            public function findByIdWithLock(int $id): ?ActivityEntity
            {
                $entity = $this->hydrated(id: $id);

                if ($this->recordOnLock) {
                    $entity->recordDeleted();
                }

                // The update and delete paths both read through here, and the
                // test inspects this instance to see whether it was drained
                // before the commit.
                $this->entityAtCommit = $entity;

                return $entity;
            }

            public function delete(ActivityEntity $activity): void {}

            public function clearLockedRecord(int $id): void {}

            public function getFilteredActivities(
                \Core\Activity\Domain\ValueObjects\ActivityFilter $filter
            ): \Core\Shared\Domain\DataObjects\PaginatedResult {
                return new \Core\Shared\Domain\DataObjects\PaginatedResult([], 0, 1, $filter->perPage, 1);
            }

            public function findById(int $id): ?\Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO
            {
                // The write paths read the saved row back to build the response.
                return new \Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO(
                    entity: $this->hydrated(id: $id),
                );
            }

            private function hydrated(int $id = 7): ActivityEntity
            {
                return ActivityEntity::reconstitute(
                    id: $id,
                    trackableType: 'lead',
                    trackableId: 42,
                    type: ActivityType::CALL,
                    subject: 'Discovery call',
                    userId: 7,
                );
            }
        };

        $rolledBack = &$this->rolledBack;

        $transactionManager = new class($commitThrows, $trackingRollback, $rolledBack) implements TransactionManagerContract {
            public function __construct(
                private readonly bool $commitThrows,
                private readonly bool $trackingRollback,
                private bool &$rolledBack,
            ) {}

            public function beginTransaction(): void {}

            public function commit(): void
            {
                // The service drains the entity before it gets here, so this is
                // the last moment the event buffer can still be observed.
                if ($this->commitThrows) {
                    throw new RuntimeException('Commit failed.');
                }
            }

            public function rollback(): void
            {
                if ($this->trackingRollback) {
                    $this->rolledBack = true;
                }
            }

            public function run(callable $callback): mixed
            {
                return $callback();
            }
        };

        $dispatched = &$this->dispatched;

        $eventDispatcher = new class($dispatched) implements EventDispatcherContract {
            /** @param array<int, object> $dispatched */
            public function __construct(private array &$dispatched) {}

            public function dispatch(object $event): void
            {
                $this->dispatched[] = $event;
            }
        };

        return new ActivityService(
            activityRepository: $repository,
            activityQuery: $repository,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $transactionManager,
            logger: $this->createMock(LoggerInterface::class),
            eventDispatcher: $eventDispatcher,
        );
    }

    private function makeUnsavedEntity(): ActivityEntity
    {
        return ActivityEntity::createNew(
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
        );
    }

    private function makeCreateDTO(): ActivityCreateDTO
    {
        return new ActivityCreateDTO(
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
        );
    }
}
