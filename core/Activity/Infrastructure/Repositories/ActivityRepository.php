<?php

declare(strict_types=1);

namespace Core\Activity\Infrastructure\Repositories;

use App\Models\Activity;
use Core\Activity\Application\Contracts\ActivityQueryContract;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityPeriod;
use Core\Activity\Domain\Enums\ActivityStatus;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Activity\Infrastructure\Mappers\ActivityEntityMapper;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;
use RuntimeException;

/**
 * Eloquent-backed persistence for activities. The only place in the module
 * where Laravel/Eloquent is used; results are mapped to Domain Entities first.
 *
 * @implements \Core\Activity\Domain\Contracts\ActivityRepositoryContract
 * @implements \Core\Activity\Application\Contracts\ActivityQueryContract
 */
final class ActivityRepository extends BaseRepository implements ActivityRepositoryContract, ActivityQueryContract
{
    /**
     * Rows read under a lock, keyed by activity ID. The lock read keeps the
     * model here so the matching save() writes back to the same instance
     * instead of fetching the row a second time.
     *
     * @var array<int, Activity>
     */
    private array $lockedModels = [];

    /**
     * @param Activity             $model
     * @param ActivityEntityMapper $mapper
     */
    public function __construct(
        Activity $model,
        private readonly ActivityEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * Persists the entity, inserting it when it has no ID yet.
     *
     * @param ActivityEntity $activity
     *
     * @return ActivityEntity
     *
     * @throws RuntimeException When the entity carries an ID that no longer exists.
     */
    public function save(ActivityEntity $activity): ActivityEntity
    {
        return $this->write($activity, $this->takeLockedRecord($activity));
    }

    /**
     * Fills and persists the given row from the entity's state.
     *
     * @param ActivityEntity $activity
     * @param Activity       $activityModel
     *
     * @return ActivityEntity
     */
    private function write(ActivityEntity $activity, Activity $activityModel): ActivityEntity
    {
        $activityModel->fill($this->toPersistence($activity));
        $activityModel->save();

        return $this->mapper->toEntity($activityModel->toArray());
    }

    /**
     * Resolves the row to write: the one a lock read already produced for this
     * ID, otherwise a fresh instance for an insert or a fetched row for an
     * update.
     *
     * @param ActivityEntity $activity
     *
     * @return Activity
     *
     * @throws RuntimeException When the entity carries an ID that no longer exists.
     */
    private function takeLockedRecord(ActivityEntity $activity): Activity
    {
        $id = $activity->getId();

        if ($id === null) {
            return new Activity();
        }

        // Consumed once: the cached row is dropped so a later save() for the
        // same ID reads the current state rather than reusing a stale model.
        if (isset($this->lockedModels[$id])) {
            $activityModel = $this->lockedModels[$id];

            unset($this->lockedModels[$id]);

            return $activityModel;
        }

        $activityModel = $this->findRecord($id);

        if (! $activityModel) {
            throw new RuntimeException(sprintf(
                'Activity with ID %d no longer exists.',
                (int) $id,
            ));
        }

        return $activityModel;
    }

    /**
     * @param ActivityFilter $filter
     *
     * @return PaginatedResult
     */
    public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
    {
        $query = Activity::with(['user', 'trackable']);

        if ($filter->status === ActivityStatus::PENDING) {
            $query->whereNull('completed_at');
        } elseif ($filter->status === ActivityStatus::COMPLETED) {
            $query->whereNotNull('completed_at');
        }

        if ($filter->type !== null) {
            $query->where('type', $filter->type->value);
        }

        if ($filter->userId !== null) {
            $query->where('user_id', $filter->userId);
        }

        if ($filter->period === ActivityPeriod::TODAY) {
            // due_date is stored as a UTC wall clock, so "today" is the UTC day
            // and the comparison stays a half-open range rather than whereDate,
            // which would apply the connection's timezone to the column.
            $dayStart = now()->startOfDay()->utc();

            $query->where('due_date', '>=', $dayStart)
                  ->where('due_date', '<', $dayStart->copy()->addDay());
        } elseif ($filter->period === ActivityPeriod::OVERDUE) {
            // SQL form of ActivityEntity::isOverdue(): pending and past due.
            $query->whereNull('completed_at')
                  ->where('due_date', '<', now());
        }

        $eloquentPaginator = $query->orderBy('due_date', 'asc')->paginate($filter->perPage);

        $entities = $eloquentPaginator
            ->getCollection()
            ->map(fn(Activity $item) => $this->mapper->toProjection($item->toArray()))
            ->all();

        return new PaginatedResult(
            items: $entities,
            total: $eloquentPaginator->total(),
            currentPage: $eloquentPaginator->currentPage(),
            perPage: $eloquentPaginator->perPage(),
            lastPage: $eloquentPaginator->lastPage(),
        );
    }

    /**
     * @param int $id
     *
     * @return ActivityProjectionDTO|null
     */
    public function findById(int $id): ?ActivityProjectionDTO
    {
        $activityModel = Activity::with(['user', 'trackable'])->find($id);

        if (! $activityModel) {
            return null;
        }

        return $this->mapper->toProjection($activityModel->toArray());
    }

    /**
     * Locks the row and returns its entity. The locked model is kept so the
     * save() that follows writes back to this same row.
     *
     * @param int $id
     *
     * @return ActivityEntity|null
     */
    public function findByIdWithLock(int $id): ?ActivityEntity
    {
        $activityModel = $this->findRecordForUpdate($id);

        if (! $activityModel) {
            return null;
        }

        $this->lockedModels[$id] = $activityModel;

        return $this->mapper->toEntity($activityModel->toArray());
    }

    /**
     * Deletes an activity.
     *
     * @param ActivityEntity $activity
     *
     * @return void
     *
     * @throws RuntimeException When the row could not be deleted.
     */
    public function delete(ActivityEntity $activity): void
    {
        if (! $this->deleteRecord((int) $activity->getId())) {
            throw new RuntimeException(sprintf(
                'Activity with ID %d could not be deleted.',
                (int) $activity->getId(),
            ));
        }
    }

    /**
     * Flattens the entity's own state onto the activity table's columns.
     *
     * @param ActivityEntity $activity
     *
     * @return array<string, mixed>
     */
    private function toPersistence(ActivityEntity $activity): array
    {
        return [
            'user_id' => $activity->getUserId(),
            'trackable_type' => $activity->getTrackableType()->toString(),
            'trackable_id' => $activity->getTrackableId(),
            'type' => $activity->getType()->value,
            'subject' => $activity->getSubject(),
            'description' => $activity->getDescription(),
            // Stored as UTC so the model's datetime cast reads back the same
            // instant regardless of the app timezone.
            'due_date' => $activity->getDueDate()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'completed_at' => $activity->getCompletedAt()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
    }
}
