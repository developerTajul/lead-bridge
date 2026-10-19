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
use Core\Activity\Domain\ValueObjects\TrackableType;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles activity use cases. Depends only on interfaces + DTOs.
 */
final class ActivityService
{
    private const ERROR_INVALID = 'ACTIVITY_INVALID';

    private const ERROR_NOT_FOUND = 'ACTIVITY_NOT_FOUND';

    private const ERROR_CREATE_FAILED = 'ACTIVITY_CREATE_FAILED';

    private const ERROR_UPDATE_FAILED = 'ACTIVITY_UPDATE_FAILED';

    private const ERROR_DELETE_FAILED = 'ACTIVITY_DELETE_FAILED';

    private const ERROR_FETCH_FAILED = 'ACTIVITY_FETCH_FAILED';

    private const ERROR_INDEX_FAILED = 'ACTIVITY_INDEX_FAILED';

    private const DEFAULT_PER_PAGE = 15;

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

            // Released before the commit so an event already raised on this
            // entity is owned by this method, not stranded on it.
            $recordedEvents = $savedEntity->releaseEvents();

            $this->transactionManager->commit();
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
                errorCode: self::ERROR_CREATE_FAILED,
            );
        }

        // Past this point the write has committed: dispatching and the read-back
        // can no longer roll it back, so neither may turn it into a failure.
        $this->dispatchEvents($recordedEvents);

        // Read back on the read side: save() returns a write-side entity, so the
        // DB-generated columns and relations only come from here.
        return Result::success(
            data: $this->readBackOrFallback($savedEntity),
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
            return Result::failure('Failed to retrieve activities.', self::ERROR_INDEX_FAILED);
        }
    }

    /**
     * Returns the paginated activity timeline of one tracked record.
     *
     * The alias is resolved against the module's own allowlist before it
     * reaches the filter, so a caller cannot use this to read activities
     * attached to an arbitrary morph type.
     *
     * @param string $trackableType Morph alias of the tracked record, e.g. "lead".
     * @param int    $trackableId   ID of the tracked record.
     * @param int    $perPage       Page size within the supported bounds.
     *
     * @return Result Contains the PaginatedResult of ActivityResponseDTO on success.
     */
    public function getActivitiesForTrackable(
        string $trackableType,
        int $trackableId,
        int $perPage = self::DEFAULT_PER_PAGE
    ): Result {
        try {
            $alias = TrackableType::fromString($trackableType)->toString();

            $activityFilter = new ActivityFilter(
                trackableType: $alias,
                trackableId: $trackableId,
                perPage: $perPage,
            );
        } catch (InvalidActivityException $exception) {
            // A rejected alias or page size is bad input, not a system failure.
            $this->logger->warning('Activity timeline rejected its criteria.', [
                'trackable_type' => $trackableType,
                'trackable_id' => $trackableId,
                'message' => $exception->getMessage(),
            ]);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        }

        try {
            $paginatedActivities = $this->activityQuery->getFilteredActivities($activityFilter);

            // Mapped through the existing response mapper, so both read paths
            // produce the same wire shape.
            $mappedPaginatedResult = $paginatedActivities->map(
                fn(ActivityProjectionDTO $projection) => $this->activityResponseMapper->toResponseDTO($projection)
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Activities retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve the activity timeline.', [
                'trackable_type' => $trackableType,
                'trackable_id' => $trackableId,
                'exception' => $exception,
            ]);

            return Result::failure('Failed to retrieve activities.', self::ERROR_INDEX_FAILED);
        }
    }


    public function getActivityById(int $id): Result
    {
        try {
            $activityProjection = $this->activityQuery->findById($id);

            if (! $activityProjection) {
                return Result::failure('Activity not found.', self::ERROR_NOT_FOUND);
            }

            $activityResponseDTO = $this->activityResponseMapper->toResponseDTO($activityProjection);

            return Result::success(
                data: $activityResponseDTO,
                message: 'Activity retrieved successfully.'
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve activity.', ['id' => $id, 'exception' => $exception]);
            return Result::failure('Failed to retrieve activity.', self::ERROR_FETCH_FAILED);
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
                    errorCode: self::ERROR_NOT_FOUND,
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
                clearDueDate: $activityUpdateDTO->clearDueDate,
                clearDescription: $activityUpdateDTO->clearDescription,
            );

            $savedEntity = $this->activityRepository->save($activityEntity);

            // save() hydrates a fresh entity, so anything the mutated instance
            // recorded has to be released before that return value replaces it.
            // Released after the save so events raised during persistence count.
            $recordedEvents = $activityEntity->releaseEvents();

            $this->transactionManager->commit();
        } catch (InvalidActivityException $exception) {
            $this->transactionManager->rollback();

            // The lock read cached a row that this rollback has invalidated.
            $this->activityRepository->clearLockedRecord($activityUpdateDTO->id);

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->activityRepository->clearLockedRecord($activityUpdateDTO->id);

            $this->logger->error('Activity update failed.', [
                'id' => $activityUpdateDTO->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to update the activity. Please try again.',
                errorCode: self::ERROR_UPDATE_FAILED,
            );
        }

        // Past this point the write has committed: dispatching and the read-back
        // can no longer roll it back, so neither may turn it into a failure.
        $this->dispatchEvents($recordedEvents);

        // Read back on the read side, for the same reason as create().
        return Result::success(
            data: $this->readBackOrFallback($savedEntity),
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
                    errorCode: self::ERROR_NOT_FOUND,
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

            // The lock read cached a row that this rollback has invalidated.
            $this->activityRepository->clearLockedRecord($id);

            $this->logger->error('Activity deletion failed.', [
                'id' => $id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to delete the activity. Please try again.',
                errorCode: self::ERROR_DELETE_FAILED,
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
     * Builds the response from a read of the committed row, falling back to the
     * saved entity when that read cannot serve one. The write is already
     * committed by this point, so a failed read must not be reported to the
     * caller as a failed write; the fallback response carries every field the
     * write itself produced, but no DB timestamp and no loaded relations.
     *
     * @param ActivityEntity $savedEntity
     *
     * @return ActivityResponseDTO
     */
    private function readBackOrFallback(ActivityEntity $savedEntity): ActivityResponseDTO
    {
        try {
            $activityProjection = $this->activityQuery->findById((int) $savedEntity->getId());

            if ($activityProjection) {
                return $this->activityResponseMapper->toResponseDTO($activityProjection);
            }

            $this->logger->error('Committed activity could not be read back, so the response was built from the saved entity.', [
                'id' => $savedEntity->getId(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Reading back a committed activity failed, so the response was built from the saved entity.', [
                'id' => $savedEntity->getId(),
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->toFallbackResponse($savedEntity);
    }

    /**
     * Builds the response straight from the saved entity, for when the read-back
     * could not serve one. Fields the entity does not carry stay null rather
     * than being invented here.
     *
     * @param ActivityEntity $savedEntity
     *
     * @return ActivityResponseDTO
     */
    private function toFallbackResponse(ActivityEntity $savedEntity): ActivityResponseDTO
    {
        return new ActivityResponseDTO(
            id: $savedEntity->getId(),
            user_id: $savedEntity->getUserId(),
            trackable_type: $savedEntity->getTrackableType()->toString(),
            trackable_id: $savedEntity->getTrackableId(),
            type: $savedEntity->getType()->value,
            subject: $savedEntity->getSubject(),
            description: $savedEntity->getDescription(),
            // Formatted the way ActivityResponseMapper does, so both paths
            // produce the same wire format.
            due_date: $savedEntity->getDueDate()?->format(DateTimeInterface::ATOM),
            completed_at: $savedEntity->getCompletedAt()?->format(DateTimeInterface::ATOM),
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
