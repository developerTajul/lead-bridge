<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for the activity index and show use cases.
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
            eventDispatcher: $this->createMock(EventDispatcherContract::class),
        );
    }

    public function test_index_returns_paginated_response_dtos(): void
    {
        $this->activityRepository->method('getFilteredActivities')
            ->willReturn($this->makePaginatedResult());

        $result = $this->activityService->getActivities(new ActivityFilterDTO());

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(PaginatedResult::class, $result->data);
        $this->assertContainsOnlyInstancesOf(ActivityResponseDTO::class, $result->data->items);
    }

    public function test_index_preserves_pagination_meta_data(): void
    {
        $this->activityRepository->method('getFilteredActivities')
            ->willReturn($this->makePaginatedResult());

        $result = $this->activityService->getActivities(new ActivityFilterDTO(perPage: 15));

        $this->assertSame(3, $result->data->total);
        $this->assertSame(2, $result->data->currentPage);
        $this->assertSame(15, $result->data->perPage);
        $this->assertSame(4, $result->data->lastPage);
    }

    public function test_index_maps_entity_fields_onto_response_dto(): void
    {
        $this->activityRepository->method('getFilteredActivities')
            ->willReturn($this->makePaginatedResult());

        $result = $this->activityService->getActivities(new ActivityFilterDTO());

        $firstItem = $result->data->items[0];

        $this->assertSame(1, $firstItem->id);
        $this->assertSame('Discovery call', $firstItem->subject);
        $this->assertSame('call', $firstItem->type);
        $this->assertSame('App\\Models\\Lead', $firstItem->trackable_type);
        $this->assertSame(42, $firstItem->trackable_id);
        $this->assertSame(7, $firstItem->user_id);
        $this->assertNull($firstItem->completed_at);
    }

    public function test_index_passes_the_filter_dto_to_the_repository(): void
    {
        $filterDTO = new ActivityFilterDTO(
            status: 'pending',
            type: 'call',
            userId: 7,
            filter: 'today',
            perPage: 25,
        );

        $this->activityRepository->expects($this->once())
            ->method('getFilteredActivities')
            ->with($filterDTO)
            ->willReturn($this->makePaginatedResult());

        $this->activityService->getActivities($filterDTO);
    }

    public function test_index_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('getFilteredActivities')
            ->willThrowException(new RuntimeException('Database is down.'));

        $result = $this->activityService->getActivities(new ActivityFilterDTO());

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_INDEX_FAILED', $result->errorCode);
        $this->assertNull($result->data);
    }

    // ---------------------------------------------------------------------
    // show()
    // ---------------------------------------------------------------------

    public function test_show_returns_success_result_with_response_dto(): void
    {
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());

        $result = $this->activityService->getActivityById(1);

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(ActivityResponseDTO::class, $result->data);
        $this->assertSame('Activity retrieved successfully.', $result->message);
        $this->assertSame('Discovery call', $result->data->subject);
    }

    public function test_show_renders_the_enum_as_a_plain_string(): void
    {
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());

        $result = $this->activityService->getActivityById(1);

        // Regression: the response DTO property is typed string, so passing the
        // raw ActivityType enum here would raise a TypeError.
        $this->assertSame('call', $result->data->type);
        $this->assertIsString($result->data->type);
    }

    public function test_show_carries_the_loaded_user_and_trackable(): void
    {
        $this->activityRepository->method('findById')->willReturn($this->makeEntity());

        $result = $this->activityService->getActivityById(1);

        $this->assertSame(['id' => 7, 'name' => 'Tajul'], $result->data->user);
        $this->assertSame(['id' => 42, 'name' => 'Acme'], $result->data->trackable);
    }

    public function test_show_returns_failure_when_activity_is_missing(): void
    {
        $this->activityRepository->method('findById')->willReturn(null);

        $result = $this->activityService->getActivityById(999);

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_NOT_FOUND', $result->errorCode);
        $this->assertSame('Activity not found.', $result->message);
        $this->assertNull($result->data);
    }

    public function test_show_returns_failure_when_repository_throws(): void
    {
        $this->activityRepository->method('findById')
            ->willThrowException(new RuntimeException('Database is down.'));

        $result = $this->activityService->getActivityById(1);

        $this->assertTrue($result->isFailure());
        $this->assertSame('ACTIVITY_FETCH_FAILED', $result->errorCode);
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
