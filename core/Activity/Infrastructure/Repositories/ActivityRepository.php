<?php

declare(strict_types=1);

namespace Core\Activity\Infrastructure\Repositories;

use App\Models\Activity;
use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\Entities\ActivityEntity;
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
 */
final class ActivityRepository extends BaseRepository implements ActivityRepositoryContract
{
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
        $activityModel = $activity->getId() === null
            ? new Activity()
            : $this->findRecord((int) $activity->getId());

        if (! $activityModel) {
            throw new RuntimeException(sprintf(
                'Activity with ID %d no longer exists.',
                (int) $activity->getId(),
            ));
        }

        $activityModel->fill($this->toPersistence($activity));
        $activityModel->save();

        return $this->mapper->toEntity($activityModel->toArray());
    }

    /**
     * @param int $perPage Items per page (default: 10).
     *
     * @return PaginatedResult
     */
    public function paginate(int $perPage = 10): PaginatedResult
    {
        $paginator = $this->paginateEloquent($perPage);

        $entities = $paginator
            ->getCollection()
            ->map(fn(Activity $item) => $this->mapper->toProjection($item->toArray()))
            ->all();

        return new PaginatedResult(
            items: $entities,
            total: $paginator->total(),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    /**
     * @param ActivityFilter $filter
     *
     * @return PaginatedResult
     */
    public function getFilteredActivities(ActivityFilter $filter): PaginatedResult
    {
        $query = Activity::with(['user', 'trackable']);

        if ($filter->status === 'pending') {
            $query->whereNull('completed_at');
        } elseif ($filter->status === 'completed') {
            $query->whereNotNull('completed_at');
        }

        if ($filter->type !== null) {
            $query->where('type', $filter->type);
        }

        if ($filter->userId !== null) {
            $query->where('user_id', $filter->userId);
        }

        if ($filter->period === 'today') {
            $query->whereDate('due_date', now()->toDateString());
        } elseif ($filter->period === 'overdue') {
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

        return $this->mapper->toEntity($activityModel->toArray());
    }

    /**
     * @param ActivityEntity $activity
     *
     * @return void
     */
    public function delete(ActivityEntity $activity): void
    {
        $this->deleteRecord((int) $activity->getId());
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
            'trackable_type' => $activity->getTrackableType(),
            'trackable_id' => $activity->getTrackableId(),
            'type' => $activity->getType()->value,
            'subject' => $activity->getSubject(),
            'description' => $activity->getDescription(),
            'due_date' => $activity->getDueDate()?->format('Y-m-d H:i:s'),
            'completed_at' => $activity->getCompletedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
