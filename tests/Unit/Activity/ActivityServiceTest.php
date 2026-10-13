<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for the create activity use case.
 */
final class ActivityServiceTest extends TestCase
{
    private ActivityRepositoryContract $activityRepository;

    private TransactionManagerContract $transactionManager;

    private ActivityService $activityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->createMock(ActivityRepositoryContract::class);
        $this->transactionManager = $this->createMock(TransactionManagerContract::class);

        $this->activityService = new ActivityService(
            activityRepository: $this->activityRepository,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $this->transactionManager,
            logger: $this->createMock(LoggerInterface::class),
        );
    }

    public function test_create_returns_success_result_with_response_dto(): void
    {
        $this->activityRepository->method('create')->willReturn($this->makeEntity());

        $result = $this->activityService->createActivity($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(ActivityResponseDTO::class, $result->data);
        $this->assertSame('Discovery call', $result->data->subject);
    }

    public function test_create_commits_the_transaction_on_success(): void
    {
        $this->activityRepository->method('create')->willReturn($this->makeEntity());

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('commit');
        $this->transactionManager->expects($this->never())->method('rollback');

        $result = $this->activityService->createActivity($this->makeCreateDTO());

        $this->assertTrue($result->isSuccess());
    }

    public function test_create_rolls_back_and_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('create')
            ->willThrowException(new RuntimeException('Database is down.'));

        $this->transactionManager->expects($this->once())->method('beginTransaction');
        $this->transactionManager->expects($this->once())->method('rollback');
        $this->transactionManager->expects($this->never())->method('commit');

        $result = $this->activityService->createActivity($this->makeCreateDTO());

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_CREATE_FAILED', $result->errorCode);
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
        return new ActivityEntity(
            id: 1,
            userId: 7,
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            description: 'Intro call with the prospect',
            dueDate: '2026-10-05 09:30:00',
            completedAt: null,
            createdAt: '2026-10-03 10:00:00',
            updatedAt: '2026-10-03 10:00:00',
        );
    }
}