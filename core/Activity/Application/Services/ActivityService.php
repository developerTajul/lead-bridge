<?php

declare(strict_types=1);

namespace Core\Activity\Application\Services;

use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Exceptions\InvalidActivityException;
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
    private const ERROR_INVALID = 'ACTIVITY_INVALID';

    public function __construct(
        private readonly ActivityRepositoryContract $activityRepository,
        private readonly ActivityQueryContract $activityQuery,
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

            $createdEntity = ActivityEntity::createNew(
                trackableType: $activityCreateDTO->trackableType,
                trackableId: $activityCreateDTO->trackableId,
                type: $activityCreateDTO->type,
                subject: $activityCreateDTO->subject,
                userId: $activityCreateDTO->userId,
                description: $activityCreateDTO->description,
                dueDate: $activityCreateDTO->dueDate,
                completedAt: $activityCreateDTO->completedAt
            );

            $savedEntity = $this->activityRepository->save($createdEntity);

            // Listeners are dispatched from the saved entity, which is the only
            // one carrying an ID. The created entity records nothing.
            $savedEntity->recordCreated();

            $this->transactionManager->commit();

            // Read back on the read side: save() returns a write-side entity,
            // so the DB-generated columns and relations only come from here.
            $activityResponseDTO = $this->projectSavedActivity((int) $savedEntity->getId());
        } catch (InvalidActivityException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
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

        // The row is committed by this point, so a read-back that came up empty
        // is a response-assembly failure, not a rolled-back create.
        if (! $activityResponseDTO) {
            return $this->savedActivityUnreadable('create');
        }

        // The activity is already committed: a failing listener must not turn
        // a successful create into an error response.
        $this->dispatchEvents($savedEntity->releaseEvents());

        return Result::success(
            data: $activityResponseDTO,
            message: 'Activity created successfully.',
        );
    }


    public function getActivities(ActivityFilterDTO $filterDTO): Result
    {
        try {
            $activityFilter = new ActivityFilter(
                status: $filterDTO->status,
                type: $filterDTO->type,
                userId: $filterDTO->userId,
                period: $filterDTO->filter,
                perPage: $filterDTO->perPage
            );
            $paginatedActivities = $this->activityQuery->getFilteredActivities($activityFilter);

            $mappedPaginatedResult = $paginatedActivities->map(
                fn(ActivityProjectionDTO $projection) => $this->activityResponseMapper->toResponseDTO($projection)
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Activities retrieved successfully.'
            );
        } catch (InvalidActivityException $exception) {
            // A rejected filter is bad input, not a system failure.
            $this->logger->warning('Activity list rejected an invalid filter.', [
                'message' => $exception->getMessage(),
            ]);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve activities.', ['exception' => $exception]);
            return Result::failure('Failed to retrieve activities.', 'ACTIVITY_INDEX_FAILED');
        }
    }



    public function getActivityById(int $id): Result
    {
        try {
            $activityProjection = $this->activityQuery->findById($id);

            if (! $activityProjection) {
                return Result::failure('Activity not found.', 'ACTIVITY_NOT_FOUND');
            }

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

            // Row-level lock. The repository holds the locked row so the save()
            // below writes back to it instead of fetching it a second time.
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

            $this->activityRepository->save($activityEntity);

            // save() hydrates a fresh entity, so anything the mutated instance
            // recorded has to be released before that return value replaces it.
            // Released after the save so events raised during persistence count.
            $recordedEvents = $activityEntity->releaseEvents();

            $this->transactionManager->commit();

            // Read back on the read side, for the same reason as create().
            $activityResponseDTO = $this->projectSavedActivity($activityUpdateDTO->id);
        } catch (InvalidActivityException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
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

        // The row is committed by this point, so a read-back that came up empty
        // is a response-assembly failure, not a rolled-back update.
        if (! $activityResponseDTO) {
            return $this->savedActivityUnreadable('update');
        }

        // The change is already committed: a failing listener must not turn a
        // successful update into an error response.
        $this->dispatchEvents($recordedEvents);

        return Result::success(
            data: $activityResponseDTO,
            message: 'Activity updated successfully.',
        );
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
        $recordedEvents = [];

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

            // delete() does not hand back a hydrated entity, but the events are
            // released here anyway so every write path drains the same way.
            $recordedEvents = $activityEntity->releaseEvents();

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
        $this->dispatchEvents($recordedEvents);

        return Result::success(
            data: null,
            message: 'Activity deleted successfully.',
        );
    }

    /**
     * Re-reads a just-saved activity through the query port and maps it to the
     * response DTO. The write side returns a domain entity only, so the
     * DB-generated columns and the loaded relations have to come from a read.
     *
     * @param int $id
     *
     * @return ActivityResponseDTO|null Null when the row cannot be read back.
     */
    private function projectSavedActivity(int $id): ?ActivityResponseDTO
    {
        $activityProjection = $this->activityQuery->findById($id);

        if (! $activityProjection) {
            return null;
        }

        return $this->activityResponseMapper->toResponseDTO($activityProjection);
    }

    /**
     * Reports a write that committed but could not be read back afterwards.
     * Only reachable if the row vanished between the commit and the read.
     *
     * @param string $operation
     *
     * @return Result
     */
    private function savedActivityUnreadable(string $operation): Result
    {
        $this->logger->error('Saved activity could not be read back.', ['operation' => $operation]);

        return Result::failure(
            message: "The activity was {$operation}d but could not be read back.",
            errorCode: 'ACTIVITY_READ_BACK_FAILED',
        );
    }

    /**
     * Dispatches already-released events after the transaction commits. A
     * listener failure is logged rather than surfaced, so it cannot override
     * an already-persisted change.
     *
     * @param array<int, object> $events
     *
     * @return void
     */
    private function dispatchEvents(array $events): void
    {
        foreach ($events as $event) {
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
