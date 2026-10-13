<?php

declare(strict_types=1);

namespace Core\Activity\Application\Services;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\DataObjects\ActivityData;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Shared\Application\DTOs\Result;
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
    ) {}

    /**
     * Creates an activity against the record it tracks.
     *
     * @param ActivityCreateDTO $activityCreateDTO
     *
     * @return Result Contains the ActivityResponseDTO on success.
     */
    public function createActivity(ActivityCreateDTO $activityCreateDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $activityData = new ActivityData(
                trackableType: $activityCreateDTO->trackableType,
                trackableId: $activityCreateDTO->trackableId,
                type: $activityCreateDTO->type,
                subject: $activityCreateDTO->subject,
                userId: $activityCreateDTO->userId,
                description: $activityCreateDTO->description,
                dueDate: $activityCreateDTO->dueDate,
                completedAt: $activityCreateDTO->completedAt,
            );

            $activityEntity = $this->activityRepository->create($activityData);

            $activityResponseDTO = $this->activityResponseMapper->toResponseDTO($activityEntity);

            $this->transactionManager->commit();

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

    /**
     * Returns a paginated list of activities.
     *
     * @param int $perPage Items per page (default: 10).
     *
     * @return Result Contains a PaginatedResult of ActivityResponseDTO on success.
     */
    public function getActivities(int $perPage = 10): Result
    {
        try {
            $paginatedEntities = $this->activityRepository->paginate($perPage);

            $paginatedResponse = $paginatedEntities->map(
                fn(ActivityEntity $entity) => $this->activityResponseMapper->toResponseDTO($entity),
            );

            return Result::success(data: $paginatedResponse);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve activities.', ['exception' => $exception]);

            return Result::failure(
                message: 'Failed to retrieve activities. Please try again.',
                errorCode: 'ACTIVITY_INDEX_FAILED',
            );
        }
    }
}