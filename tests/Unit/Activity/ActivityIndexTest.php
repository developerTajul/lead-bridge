<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for the activity index use case.
 */
final class ActivityIndexTest extends TestCase
{
    private ActivityRepositoryContract $activityRepository;

    private ActivityService $activityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->createMock(ActivityRepositoryContract::class);

        $this->activityService = new ActivityService(
            activityRepository: $this->activityRepository,
            activityResponseMapper: new ActivityResponseMapper(),
            transactionManager: $this->createMock(TransactionManagerContract::class),
            logger: $this->createMock(LoggerInterface::class),
        );
    }

    public function test_index_returns_paginated_response_dtos(): void
    {
        $this->activityRepository->method('paginate')->willReturn($this->makePaginatedResult());

        $result = $this->activityService->getActivities(10);

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(PaginatedResult::class, $result->data);
        $this->assertContainsOnlyInstancesOf(ActivityResponseDTO::class, $result->data->items);
    }

    public function test_index_preserves_pagination_meta_data(): void
    {
        $this->activityRepository->method('paginate')->willReturn($this->makePaginatedResult());

        $result = $this->activityService->getActivities(15);

        $this->assertSame(3, $result->data->total);
        $this->assertSame(2, $result->data->currentPage);
        $this->assertSame(15, $result->data->perPage);
        $this->assertSame(4, $result->data->lastPage);
    }

    public function test_index_maps_entity_fields_onto_response_dto(): void
    {
        $this->activityRepository->method('paginate')->willReturn($this->makePaginatedResult());

        $result = $this->activityService->getActivities();

        $firstItem = $result->data->items[0];

        $this->assertSame('Discovery call', $firstItem->subject);
        $this->assertSame('call', $firstItem->type);
        $this->assertSame('App\\Models\\Lead', $firstItem->trackableType);
        $this->assertSame(42, $firstItem->trackableId);
        $this->assertNull($firstItem->completedAt);
    }

    public function test_index_returns_failure_result_when_repository_throws(): void
    {
        $this->activityRepository->method('paginate')
            ->willThrowException(new RuntimeException('Database is down.'));

        $result = $this->activityService->getActivities();

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_INDEX_FAILED', $result->errorCode);
        $this->assertNull($result->data);
    }

    private function makePaginatedResult(): PaginatedResult
    {
        return new PaginatedResult(
            items: [$this->makeEntity()],
            total: 3,
            currentPage: 2,
            perPage: 15,
            lastPage: 4,
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