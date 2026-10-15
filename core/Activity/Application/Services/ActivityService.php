<?php

declare(strict_types=1);

namespace Core\Activity\Application\Services;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\DataObjects\ActivityData;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Events\ActivityCreatedEvent;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Psr\Log\LoggerInterface;

/**
 * Handles activity use cases. Depends only on interfaces + DTOs.
 */
final class ActivityService
{
    public function __construct(
        private readonly ActivityRepositoryContract $activityRepository,
        private readonly ActivityResponseMapper $activityResponseMapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherContract $eventDispatcher
    ) {}

    /**
     * Creates an activity against the record it tracks.
     *
     * @param ActivityCreateDTO $activityCreateDTO
     *
     * @return Result Contains the ActivityResponseDTO on success.
     */
    public function create(ActivityCreateDTO $activityCreateDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $activityEntity = ActivityEntity::createNew(                
                trackableType: $activityCreateDTO->trackableType,
                trackableId: $activityCreateDTO->trackableId,
                type: $activityCreateDTO->type,
                subject: $activityCreateDTO->subject,
                userId: $activityCreateDTO->userId,
                description: $activityCreateDTO->description,
                dueDate: $activityCreateDTO->dueDate ? new \DateTimeImmutable($activityCreateDTO->dueDate) : null
            );

            $activityData = new ActivityData(
                trackableType: $activityEntity->getTrackableType(),
                trackableId: $activityEntity->getTrackableId(),
                type: $activityEntity->getType(),
                subject: $activityEntity->getSubject(),
                userId: $activityEntity->getUserId(),
                description: $activityEntity->getDescription(),
                dueDate: $activityEntity->getDueDate()?->format('Y-m-d H:i:s'),
                completedAt: $activityEntity->getCompletedAt()?->format('Y-m-d H:i:s'),
            );

            $savedEntity = $this->activityRepository->create( $activityData );

            $activityResponseDTO = $this->activityResponseMapper->toResponseDTO( $savedEntity );

            $this->transactionManager->commit();

            $this->eventDispatcher->dispatch(new ActivityCreatedEvent(
                activityId: $savedEntity->getId(),
                trackableType: $savedEntity->getTrackableType(),
                trackableId: $savedEntity->getTrackableId()
            ));

            return Result::success(
                data: $activityResponseDTO,
                message: 'Activity created successfully.',
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Activity creation failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to create the activity. Please try again.',
                errorCode: 'ACTIVITY_CREATE_FAILED',
            );
        }
    }


public function getActivities(ActivityFilterDTO $filterDTO): Result
    {
        try {
            // ১. রিপোজিটরি থেকে ডাটা নিয়ে আসা (এর items এর ভেতরে ActivityEntity আছে)
            $paginatedActivities = $this->activityRepository->getFilteredActivities($filterDTO);

            // ২. ভেতরের Entity গুলোকে ActivityResponseDTO তে রূপান্তর করা
            $mappedItems = array_map(
                fn($entity) => $this->activityResponseMapper->toResponseDTO($entity),
                $paginatedActivities->items
            );

            // ৩. ম্যাপ করা ডাটা দিয়ে নতুন একটি PaginatedResult তৈরি করা
            $mappedPaginatedResult = new \Core\Shared\Domain\DataObjects\PaginatedResult(
                items: $mappedItems,
                total: $paginatedActivities->total,
                currentPage: $paginatedActivities->currentPage,
                perPage: $paginatedActivities->perPage,
                lastPage: $paginatedActivities->lastPage
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Activities retrieved successfully.'
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve activities.', ['exception' => $exception]);
            return Result::failure('Failed to retrieve activities.', 'ACTIVITY_INDEX_FAILED');
        }
    }


}