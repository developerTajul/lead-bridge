<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
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

    private TransactionManagerContract $transactionManager;

    private EventDispatcherContract $eventDispatcher;

    private ActivityService $activityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->createMock(ActivityRepositoryContract::class);
        $this->transactionManager = $this->createMock(TransactionManagerContract::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherContract::class);

        $this->activityService = new ActivityService(
            activityRepository: $this->activityRepository,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $this->transactionManager,
            logger: $this->createMock(LoggerInterface::class),
            eventDispatcher: $this->eventDispatcher,
        );
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
        $this->assertSame('App\\Models\\Lead', $captured->getTrackableType());
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
        $this->assertSame('ACTIVITY_CREATE_FAILED', $result->errorCode);
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
        $locked = $this->makeEntity();
        $this->activityRepository->method('findByIdWithLock')->willReturn($locked);
        $this->activityRepository->method('save')->willReturn($locked);

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
        $this->activityRepository->method('findByIdWithLock')->willReturn($locked);

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
            dueDate: '2026-12-01 10:00:00',
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
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

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
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $captured = null;
        $this->activityRepository->method('save')
            ->willReturnCallback(function (ActivityEntity $entity) use (&$captured) {
                $captured = $entity;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            subject: 'Only subject',
            completedAt: '2026-10-04 08:15:00',
        ));

        $this->assertFalse($captured->isPending());
        $this->assertSame('2026-10-04 08:15:00', $captured->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_update_returns_failure_when_the_completion_time_lies_in_the_future(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->activityRepository->expects($this->never())->method('save');

        $result = $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            completedAt: '2099-01-01 00:00:00',
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_UPDATE_FAILED', $result->errorCode);
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
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
        $this->activityRepository->method('save')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'X'));

        $this->assertTrue($result->isSuccess());
    }

    public function test_update_rolls_back_and_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
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

    public function test_update_returns_failure_when_due_date_is_unparseable(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->activityRepository->expects($this->never())->method('save');

        $result = $this->activityService->update(
            new ActivityUpdateDTO(id: 1, dueDate: 'not-a-date')
        );

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_UPDATE_FAILED', $result->errorCode);
    }

    public function test_update_returns_failure_when_an_empty_subject_breaks_the_invariant(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('rollback');
        $this->activityRepository->expects($this->never())->method('save');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: ''));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_UPDATE_FAILED', $result->errorCode);
    }

    // ---------------------------------------------------------------------
    // deleteActivity()
    // ---------------------------------------------------------------------

    public function test_delete_returns_success_result(): void
    {
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());
        $this->activityRepository->expects($this->once())->method('delete');

        $result = $this->activityService->deleteActivity(1);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Activity deleted successfully.', $result->message);
        $this->assertNull($result->data);
    }

    public function test_delete_passes_the_found_entity_to_the_repository(): void
    {
        $entity = $this->makeEntity();
        $this->activityRepository->method('findById')->willReturn($entity);

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
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->deleteActivity(1);

        $this->assertTrue($result->isSuccess());
    }

    public function test_delete_dispatches_the_deleted_event_after_commit(): void
    {
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ActivityDeletedEvent::class));

        $this->activityService->deleteActivity(1);
    }

    public function test_delete_returns_failure_when_activity_is_missing(): void
    {
        $this->activityRepository->method('findById')->willReturn(null);

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
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());
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
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());
        $this->activityRepository->method('delete')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->activityService->deleteActivity(1);
    }

    private function makeCreateDTO(
        string $subject = 'Discovery call',
        ?string $completedAt = null,
    ): ActivityCreateDTO {
        return new ActivityCreateDTO(
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: $subject,
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: '2026-10-05 09:30:00',
            completedAt: $completedAt,
        );
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
            completedAt: null,
        )->withProjection(
            user: ['id' => 7, 'name' => 'Tajul'],
            trackable: ['id' => 42, 'name' => 'Acme'],
            createdAt: new \DateTimeImmutable('2026-10-03 10:00:00'),
            updatedAt: new \DateTimeImmutable('2026-10-03 10:00:00'),
        );
    }
}
