<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Events\ActivityDeletedEvent;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for the create and update activity use cases.
 */
final class ActivityServiceTest extends TestCase
{
    private ActivityRepositoryContract $activityRepository;

    private ActivityQueryContract $activityQuery;

    private TransactionManagerContract $transactionManager;

    private EventDispatcherContract $eventDispatcher;

    private ActivityService $activityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->createMock(ActivityRepositoryContract::class);
        $this->activityQuery = $this->createMock(ActivityQueryContract::class);
        $this->transactionManager = $this->createMock(TransactionManagerContract::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherContract::class);

        $this->activityService = new ActivityService(
            activityRepository: $this->activityRepository,
            activityQuery: $this->activityQuery,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $this->transactionManager,
            logger: $this->createMock(LoggerInterface::class),
            eventDispatcher: $this->eventDispatcher,
        );

        // Both write paths read the saved row back through the query port to
        // build the response, so every test needs that read stubbed.
        $this->activityQuery->method('findById')
            ->willReturn(new ActivityProjectionDTO(entity: $this->makeEntity()));
    }

    public function test_create_returns_success_result_with_response_dto(): void
    {
        $this->activityRepository->method('save')->willReturn($this->makeEntity());

        $result = $this->activityService->create($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(ActivityResponseDTO::class, $result->data);
        $this->assertSame('Discovery call', $result->data->subject);
        $this->assertSame('call', $result->data->type);
    }

    public function test_create_commits_the_transaction_on_success(): void
    {
        $this->activityRepository->method('save')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->create($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
    }

    public function test_create_hands_the_new_entity_straight_to_the_repository(): void
    {
        $captured = null;
        $this->activityRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->create($this->makeCreateDTO());

        $this->assertNotNull($captured);
        $this->assertNull($captured->getId());
        $this->assertSame('lead', $captured->getTrackableType()->toString());
        $this->assertSame(42, $captured->getTrackableId());
        $this->assertSame(ActivityType::CALL, $captured->getType());
        $this->assertSame('Discovery call', $captured->getSubject());
        $this->assertSame(7, $captured->getUserId());
        $this->assertSame('Intro call with the prospect', $captured->getDescription());
        $this->assertSame('2026-10-05 09:30:00', $captured->getDueDate()?->format('Y-m-d H:i:s'));
    }

    public function test_create_carries_the_completed_at_from_the_dto_onto_the_entity(): void
    {
        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->create($this->makeCreateDTO(completedAt: '2026-10-04 08:15:00'));

        $this->assertSame('2026-10-04 08:15:00', $captured->getCompletedAt()?->format('Y-m-d H:i:s'));
        $this->assertFalse($captured->isPending());
    }

    public function test_create_leaves_completed_at_null_when_the_dto_omits_it(): void
    {
        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->create($this->makeCreateDTO());

        $this->assertNull($captured->getCompletedAt());
        $this->assertTrue($captured->isPending());
    }

    public function test_create_dispatches_the_created_event_on_success(): void
    {
        $this->activityRepository->method('save')->willReturn($this->makeEntity());

        $this->eventDispatcher->expects($this->once())->method('dispatch');

        $this->activityService->create($this->makeCreateDTO());
    }

    public function test_create_still_reports_success_when_a_listener_throws(): void
    {
        $this->activityRepository->method('save')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $this->eventDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('Listener blew up.'));

        $result = $this->activityService->create($this->makeCreateDTO());

        // The row is committed: a failing listener must not turn this into an error.
        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(ActivityResponseDTO::class, $result->data);
    }

    public function test_create_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('save')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');

        $result = $this->activityService->create($this->makeCreateDTO());

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_CREATE_FAILED', $result->errorCode);
        $this->assertNull($result->data);
    }

    public function test_create_returns_failure_when_the_entity_rejects_the_input(): void
    {
        // The entity guards its own invariants: a blank subject never reaches the repository.
        $this->activityRepository->expects($this->never())->method('save');

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');

        $result = $this->activityService->create($this->makeCreateDTO(subject: ''));

        $this->assertTrue($result->isFailure());
        // A broken invariant is a validation failure, not a system error.
        $this->assertSame('ACTIVITY_INVALID', $result->errorCode);
        $this->assertSame('An activity must carry a subject.', $result->message);
    }

    public function test_create_does_not_dispatch_the_event_when_repository_throws(): void
    {
        $this->activityRepository->method('save')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->activityService->create($this->makeCreateDTO());
    }

    // ---------------------------------------------------------------------
    // update()
    // ---------------------------------------------------------------------

    public function test_update_returns_success_result_with_response_dto(): void
    {
        $this->willLock($this->makeEntity());

        $result = $this->activityService->update(
            new ActivityUpdateDTO(id: 1, subject: 'Updated subject')
        );

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(ActivityResponseDTO::class, $result->data);
        $this->assertSame('Activity updated successfully.', $result->message);
    }

    public function test_update_locks_the_row_before_mutating(): void
    {
        $this->activityRepository->expects($this->once())
            ->method('findByIdWithLock')
            ->with(1)
            ->willReturn($this->makeEntity());

        $this->activityRepository->method('save')->willReturn($this->makeEntity());

        $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject'));
    }

    public function test_update_mutates_the_locked_entity_and_persists_it(): void
    {
        $locked = $this->makeEntity();
        $this->willLock($locked);

        $captured = null;
        $this->activityRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            subject: 'Updated subject',
            type: ActivityType::MEETING,
            description: 'Updated description',
            dueDate: new \DateTimeImmutable('2026-12-01 10:00:00'),
        ));

        // The very entity read under the lock is the one handed back for persistence.
        $this->assertSame($locked, $captured);
        $this->assertSame('Updated subject', $captured->getSubject());
        $this->assertSame(ActivityType::MEETING, $captured->getType());
        $this->assertSame('Updated description', $captured->getDescription());
        $this->assertSame('2026-12-01 10:00:00', $captured->getDueDate()?->format('Y-m-d H:i:s'));
    }

    public function test_update_leaves_unsupplied_fields_unchanged(): void
    {
        $this->willLock($this->makeEntity());

        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'Only subject'));

        $this->assertSame('Only subject', $captured->getSubject());
        // Everything else survives from the locked entity.
        $this->assertSame(ActivityType::CALL, $captured->getType());
        $this->assertSame('Intro call with the prospect', $captured->getDescription());
        $this->assertSame('2026-10-05 09:30:00', $captured->getDueDate()?->format('Y-m-d H:i:s'));
        $this->assertNull($captured->getCompletedAt());
    }

    public function test_update_completes_the_activity_when_a_completed_at_is_supplied(): void
    {
        $this->willLock($this->makeEntity());

        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            subject: 'Only subject',
            completedAt: new \DateTimeImmutable('2026-10-04 08:15:00'),
        ));

        $this->assertFalse($captured->isPending());
        $this->assertSame('2026-10-04 08:15:00', $captured->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_update_reopens_the_activity_when_the_dto_asks_for_it(): void
    {
        // A reopen only has anything to clear on a completed row, which is what
        // the lock read hands back here.
        $locked = $this->makeEntity(completedAt: '2026-10-02 11:00:00');

        $this->willLock($locked);

        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(id: 1, reopen: true));

        $this->assertTrue($captured->isPending());
        $this->assertNull($captured->getCompletedAt());
    }

    public function test_update_returns_failure_when_the_completion_time_lies_in_the_future(): void
    {
        $this->willLock($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->activityRepository->expects($this->never())->method('save');

        $result = $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            completedAt: new \DateTimeImmutable('2099-01-01 00:00:00'),
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_INVALID', $result->errorCode);
        $this->assertStringContainsString('cannot be completed in the future', $result->message);
    }

    public function test_update_clears_the_nullable_fields_when_the_dto_asks_for_it(): void
    {
        $this->willLock($this->makeEntity());

        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            clearDueDate: true,
            clearDescription: true,
        ));

        $this->assertNull($captured->getDueDate());
        $this->assertNull($captured->getDescription());
    }

    /**
     * A payload carrying reopen: true at an activity that is already pending
     * asks for the state it is already in, so it is a no-op rather than a
     * refusal. Clients send the flag with whatever else changed.
     */
    public function test_update_treats_a_reopen_of_a_pending_activity_as_a_no_op(): void
    {
        $this->willLock($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, reopen: true));

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($captured->isPending());
        $this->assertNull($captured->getCompletedAt());
    }

    public function test_update_returns_failure_when_a_due_date_is_replaced_and_cleared_at_once(): void
    {
        $this->willLock($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->activityRepository->expects($this->never())->method('save');

        $result = $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            dueDate: new \DateTimeImmutable('2026-12-01 10:00:00'),
            clearDueDate: true,
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_INVALID', $result->errorCode);
    }

    /**
     * A state transition the domain refuses must reach the caller as a domain
     * refusal rather than a generic update failure. That mapping is also what
     * makes the delivery layer answer 422 instead of 400.
     */
    public function test_update_maps_a_refused_transition_to_an_invalid_failure(): void
    {
        $pending = $this->makeEntity();

        // The real sequence: the domain asks whether the activity is pending,
        // then transitions it. Reproducing the transition here lets the test
        // reach the service's catch for a domain refusal.
        $this->willLock($pending);
        $this->willRefuseTransitionOnSave($pending);

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'New subject'));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_INVALID', $result->errorCode);
        $this->assertSame('The activity is not completed, so it cannot be reopened.', $result->message);
    }

    /**
     * Makes save() transition the locked entity through reopen(), which a
     * pending activity refuses.
     *
     * @param ActivityEntity $entity
     *
     * @return void
     */
    private function willRefuseTransitionOnSave(ActivityEntity $entity): void
    {
        $this->activityRepository->method('save')
            ->willReturnCallback(function () use ($entity): ActivityEntity {
                $entity->reopen();

                return $entity;
            });
    }

    public function test_update_returns_failure_when_activity_is_missing(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn(null);

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');
        $this->activityRepository->expects($this->never())->method('save');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 999));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_NOT_FOUND', $result->errorCode);
    }

    public function test_update_commits_the_transaction_on_success(): void
    {
        $this->willLock($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'X'));

        $this->assertTrue($result->isSuccess());
    }

    public function test_update_rolls_back_and_returns_failure_when_repository_throws(): void
    {
        $this->willLock($this->makeEntity());
        $this->activityRepository->method('save')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'X'));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_UPDATE_FAILED', $result->errorCode);
        $this->assertNull($result->data);
    }

    public function test_update_returns_failure_when_an_empty_subject_breaks_the_invariant(): void
    {
        $this->willLock($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->activityRepository->expects($this->never())->method('save');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: ''));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_INVALID', $result->errorCode);
        $this->assertSame('An activity must carry a subject.', $result->message);
    }

    // ---------------------------------------------------------------------
    // deleteActivity()
    // ---------------------------------------------------------------------

    public function test_delete_returns_success_result(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
        $this->activityRepository->expects($this->once())->method('delete');

        $result = $this->activityService->deleteActivity(1);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Activity deleted successfully.', $result->message);
        $this->assertNull($result->data);
    }

    public function test_delete_passes_the_found_entity_to_the_repository(): void
    {
        $entity = $this->makeEntity();
        $this->activityRepository->method('findByIdWithLock')->willReturn($entity);

        $captured = null;
        $this->activityRepository->expects($this->once())
            ->method('delete')
            ->willReturnCallback(function (ActivityEntity $activity) use (&$captured) {
                $captured = $activity;
            });

        $this->activityService->deleteActivity(1);

        $this->assertSame($entity, $captured);
    }

    public function test_delete_commits_the_transaction_on_success(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->deleteActivity(1);

        $this->assertTrue($result->isSuccess());
    }

    public function test_delete_dispatches_the_deleted_event_after_commit(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ActivityDeletedEvent::class));

        $this->activityService->deleteActivity(1);
    }

    public function test_delete_still_reports_success_when_a_listener_throws(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $this->eventDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('Listener blew up.'));

        $result = $this->activityService->deleteActivity(1);

        // The row is deleted: a failing listener must not turn this into an error.
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Activity deleted successfully.', $result->message);
    }

    public function test_delete_returns_failure_when_activity_is_missing(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn(null);

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');
        $this->activityRepository->expects($this->never())->method('delete');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $result = $this->activityService->deleteActivity(999);

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_NOT_FOUND', $result->errorCode);
        $this->assertNull($result->data);
    }

    public function test_delete_rolls_back_and_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
        $this->activityRepository->method('delete')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');

        $result = $this->activityService->deleteActivity(1);

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_DELETE_FAILED', $result->errorCode);
        $this->assertNull($result->data);
    }

    public function test_delete_does_not_dispatch_the_event_when_repository_throws(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
        $this->activityRepository->method('delete')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->activityService->deleteActivity(1);
    }

    /**
     * Stubs the repository's locked read and save, which the update path uses.
     * The read-back through the query port is stubbed in setUp().
     *
     * @param ActivityEntity|null $entity Null means the row does not exist.
     */
    private function willLock(?ActivityEntity $entity): void
    {
        $this->activityRepository->method('save')->willReturn($this->makeEntity());

        if ($entity === null) {
            $this->activityRepository->method('findByIdWithLock')->willReturn(null);

            return;
        }

        $this->activityRepository->method('findByIdWithLock')->willReturn($entity);
    }

    private function makeCreateDTO(
        string $subject = 'Discovery call',
        ?string $completedAt = null,
    ): ActivityCreateDTO {
        return new ActivityCreateDTO(
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: $subject,
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: new \DateTimeImmutable('2026-10-05 09:30:00'),
            completedAt: $completedAt !== null ? new \DateTimeImmutable($completedAt) : null,
        );
    }

    private function makeEntity(?string $completedAt = null): ActivityEntity
    {
        return ActivityEntity::reconstitute(
            id: 1,
            trackableType: 'lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: new \DateTimeImmutable('2026-10-05 09:30:00'),
            completedAt: $completedAt !== null ? new \DateTimeImmutable($completedAt) : null,
        );
    }
}
