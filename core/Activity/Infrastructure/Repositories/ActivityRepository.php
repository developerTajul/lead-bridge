<?php

declare(strict_types=1);

namespace Core\Activity\Infrastructure\Repositories;

use App\Models\Activity;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\DataObjects\ActivityData;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Infrastructure\Mappers\ActivityEntityMapper;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;

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
     * @param ActivityData $activityData
     *
     * @return ActivityEntity
     */
    public function create(ActivityData $activityData): ActivityEntity
    {
        $activityModel = $this->createRecord($activityData->toArray());

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
            ->map(fn(Activity $item) => $this->mapper->toEntity($item->toArray()))
            ->all();

        return new PaginatedResult(
            items: $entities,
            total: $paginator->total(),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            lastPage: $paginator->lastPage(),
        );
    }
}