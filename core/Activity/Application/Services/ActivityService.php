<?php

declare(strict_types=1);

namespace Core\Activity\Application\Services;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
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
                dueDate: $activityCreateDTO->dueDate,
                completedAt: $activityCreateDTO->completedAt
            );

            $savedEntity = $this->activityRepository->save($activityEntity);

            $activityResponseDTO = $this->activityResponseMapper->toResponseDTO(
                new ActivityProjectionDTO(entity: $savedEntity)
            );

            $savedEntity->recordCreated();

            $this->transactionManager->commit();
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

        // The activity is already committed: a failing listener must not turn
        // a successful create into an error response.
        $this->dispatchRecordedEvents($savedEntity);

        return Result::success(
            data: $activityResponseDTO,
            message: 'Activity created successfully.',
        );
    }


    public function getActivities(ActivityFilterDTO $filterDTO): Result
    {
        try {
            // ১. Application DTO থেকে Domain criteria বানানো
            $activityFilter = new ActivityFilter(
                status: $filterDTO->status,
                type: $filterDTO->type,
                userId: $filterDTO->userId,
                period: $filterDTO->filter,
                perPage: $filterDTO->perPage
            );

            // ২. রিপোজিটরি থেকে ডাটা নিয়ে আসা (এর items এর ভেতরে read model আছে)
            $paginatedActivities = $this->activityRepository->getFilteredActivities($activityFilter);

            // ৩. ভেতরের read model গুলোকে ActivityResponseDTO তে রূপান্তর করা
            $mappedPaginatedResult = $paginatedActivities->map(
                fn(ActivityProjectionDTO $projection) => $this->activityResponseMapper->toResponseDTO($projection)
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



    public function getActivityById(int $id): Result
    {
        try {
            $activityProjection = $this->activityRepository->findById($id);

            // যদি ডেটা না পাওয়া যায়
            if (! $activityProjection) {
                return Result::failure('Activity not found.', 'ACTIVITY_NOT_FOUND');
            }

            // ডেটা পাওয়া গেলে রেসপন্স ম্যাপার দিয়ে DTO-তে কনভার্ট করা
            $activityResponseDTO = $this->activityResponseMapper->toResponseDTO($activityProjection);

            return Result::success(
                data: $activityResponseDTO,
                message: 'Activity retrieved successfully.'
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve activity.', ['id' => $id, 'exception' => $exception]);
            return Result::failure('Failed to retrieve activity.', 'ACTIVITY_FETCH_FAILED');
        }
    }


    /**
     * Updates an existing activity inside a transaction.
     *
     * @param ActivityUpdateDTO $activityUpdateDTO
     *
     * @return Result Contains the ActivityResponseDTO on success.
     */
    public function update(ActivityUpdateDTO $activityUpdateDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            // Row-level lock: the entity read here is the one we mutate and save.
            $activityEntity = $this->activityRepository->findByIdWithLock($activityUpdateDTO->id);

            if (! $activityEntity) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: "Activity not found with ID: {$activityUpdateDTO->id}",
                    errorCode: 'ACTIVITY_NOT_FOUND',
                );
            }

            // Tell the entity to mutate itself; it guards its own invariants.
            $activityEntity->applyChanges(
                subject: $activityUpdateDTO->subject,
                type: $activityUpdateDTO->type,
                description: $activityUpdateDTO->description,
                dueDate: $activityUpdateDTO->dueDate,
                completedAt: $activityUpdateDTO->completedAt,
                reopen: $activityUpdateDTO->reopen,
            );

            // The mutated entity is handed straight to the repository.
            $updatedActivity = $this->activityRepository->save($activityEntity);

            $activityResponseDTO = $this->activityResponseMapper->toResponseDTO(
                new ActivityProjectionDTO(entity: $updatedActivity)
            );

            $this->transactionManager->commit();

            return Result::success(
                data: $activityResponseDTO,
                message: 'Activity updated successfully.',
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Activity update failed.', [
                'id' => $activityUpdateDTO->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to update the activity. Please try again.',
                errorCode: 'ACTIVITY_UPDATE_FAILED',
            );
        }
    }

    /**
     * Deletes an existing activity inside a transaction.
     *
     * @param int $id
     *
     * @return Result
     */
    public function deleteActivity(int $id): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            // The delete path needs the entity itself, not the read model.
            $activityEntity = $this->activityRepository->findByIdWithLock($id);

            if (! $activityEntity) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: "Activity not found with ID: {$id}",
                    errorCode: 'ACTIVITY_NOT_FOUND',
                );
            }

            $activityEntity->recordDeleted();

            $this->activityRepository->delete($activityEntity);

            $this->transactionManager->commit();
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Activity deletion failed.', [
                'id' => $id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to delete the activity. Please try again.',
                errorCode: 'ACTIVITY_DELETE_FAILED',
            );
        }

        // The activity is already committed: a failing listener must not turn
        // a successful delete into an error response.
        $this->dispatchRecordedEvents($activityEntity);

        return Result::success(
            data: null,
            message: 'Activity deleted successfully.',
        );
    }

    /**
     * Dispatches the events the entity recorded. Runs after the transaction
     * commits, and a listener failure is logged rather than surfaced, so it
     * cannot override an already-persisted change.
     *
     * @param ActivityEntity $activityEntity
     *
     * @return void
     */
    private function dispatchRecordedEvents(ActivityEntity $activityEntity): void
    {
        foreach ($activityEntity->releaseEvents() as $event) {
            try {
                $this->eventDispatcher->dispatch($event);
            } catch (\Throwable $exception) {
                $this->logger->error('Activity event listener failed.', [
                    'event' => $event::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
