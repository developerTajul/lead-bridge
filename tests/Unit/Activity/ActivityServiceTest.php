<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\DataObjects\ActivityUpdateData;
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
        $this->activityRepository->method('create')->willReturn($this->makeEntity());

        $result = $this->activityService->create($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(ActivityResponseDTO::class, $result->data);
        $this->assertSame('Discovery call', $result->data->subject);
        $this->assertSame('call', $result->data->type);
    }

    public function test_create_commits_the_transaction_on_success(): void
    {
        $this->activityRepository->method('create')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->create($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
    }

    public function test_create_dispatches_the_created_event_on_success(): void
    {
        $this->activityRepository->method('create')->willReturn($this->makeEntity());

        $this->eventDispatcher->expects($this->once())->method('dispatch');

        $this->activityService->create($this->makeCreateDTO());
    }

    public function test_create_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('create')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');

        $result = $this->activityService->create($this->makeCreateDTO());

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_CREATE_FAILED', $result->errorCode);
        $this->assertNull($result->data);
    }

    public function test_create_does_not_dispatch_the_event_when_repository_throws(): void
    {
        $this->activityRepository->method('create')
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
        $this->activityRepository->method('update')->willReturn($locked);

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

        $this->activityRepository->method('update')->willReturn($this->makeEntity());

        $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'Updated subject'));
    }

    public function test_update_persists_only_the_mutable_fields(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $captured = null;
        $this->activityRepository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (int $id, ActivityUpdateData $data) use (&$captured) {
                $captured = $data;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(
            id: 1,
            subject: 'Updated subject',
            type: ActivityType::MEETING,
            description: 'Updated description',
            dueDate: '2026-12-01 10:00:00',
            completedAt: '2026-12-02 11:00:00',
        ));

        $this->assertNotNull($captured);
        $this->assertSame('Updated subject', $captured->subject);
        $this->assertSame(ActivityType::MEETING, $captured->type);
        $this->assertSame('Updated description', $captured->description);
        $this->assertSame('2026-12-01 10:00:00', $captured->dueDate);
        $this->assertSame('2026-12-02 11:00:00', $captured->completedAt);
    }

    public function test_update_data_never_carries_trackable_or_user_columns(): void
    {
        $payload = (new ActivityUpdateData(subject: 'Only subject'))->toArray();

        $this->assertSame(
            ['subject', 'type', 'description', 'due_date', 'completed_at'],
            array_keys($payload)
        );
        $this->assertArrayNotHasKey('trackable_type', $payload);
        $this->assertArrayNotHasKey('trackable_id', $payload);
        $this->assertArrayNotHasKey('user_id', $payload);
    }

    public function test_update_leaves_unsupplied_fields_unchanged(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());

        $captured = null;
        $this->activityRepository->method('update')
            ->willReturnCallback(function (int $id, ActivityUpdateData $data) use (&$captured) {
                $captured = $data;

                return $this->makeEntity();
            });

        $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'Only subject'));

        $this->assertSame('Only subject', $captured->subject);
        // Everything else survives from the locked entity.
        $this->assertSame(ActivityType::CALL, $captured->type);
        $this->assertSame('Intro call with the prospect', $captured->description);
        $this->assertSame('2026-10-05 09:30:00', $captured->dueDate);
        $this->assertNull($captured->completedAt);
    }

    public function test_update_returns_failure_when_activity_is_missing(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn(null);

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');
        $this->activityRepository->expects($this->never())->method('update');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 999));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_NOT_FOUND', $result->errorCode);
    }

    public function test_update_returns_failure_when_repository_update_returns_null(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
        $this->activityRepository->method('update')->willReturn(null);

        $this->transactionManager->expects($this->once())->method('rollback');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'X'));

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_NOT_FOUND', $result->errorCode);
    }

    public function test_update_commits_the_transaction_on_success(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
        $this->activityRepository->method('update')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->update(new ActivityUpdateDTO(id: 1, subject: 'X'));

        $this->assertTrue($result->isSuccess());
    }

    public function test_update_rolls_back_and_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('findByIdWithLock')->willReturn($this->makeEntity());
        $this->activityRepository->method('update')
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

        $result = $this->activityService->update(
            new ActivityUpdateDTO(id: 1, dueDate: 'not-a-date')
        );

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

    private function makeCreateDTO(): ActivityCreateDTO
    {
        return new ActivityCreateDTO(
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: '2026-10-05 09:30:00',
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
