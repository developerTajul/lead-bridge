<?php

declare(strict_types=1);

namespace Core\Activity\Infrastructure\Repositories;

use App\Models\Activity;
use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Domain\DataObjects\ActivityData;
use Core\Activity\Domain\DataObjects\ActivityUpdateData;
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
        // $activityModel = $this->createRecord( $activityData->toArray() );

        $activityModel = new Activity();
        $activityModel->user_id = $activityData->userId;
        $activityModel->trackable_type = $activityData->trackableType;
        $activityModel->trackable_id = $activityData->trackableId;
        $activityModel->type = $activityData->type->value;
        $activityModel->subject = $activityData->subject;
        $activityModel->description = $activityData->description;
        $activityModel->due_date = $activityData->dueDate;
        $activityModel->completed_at = $activityData->completedAt;
        
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


    public function getFilteredActivities(ActivityFilterDTO $filterDTO): PaginatedResult
    {
        $query = Activity::with(['user', 'trackable']);

        // ১. স্ট্যাটাস ফিল্টার
        if ($filterDTO->status === 'pending') {
            $query->whereNull('completed_at');
        } elseif ($filterDTO->status === 'completed') {
            $query->whereNotNull('completed_at');
        }

        // ২. টাইপ ফিল্টার
        if ($filterDTO->type !== null) {
            $query->where('type', $filterDTO->type);
        }

        // ৩. ইউজার ফিল্টার
        if ($filterDTO->userId !== null) {
            $query->where('user_id', $filterDTO->userId);
        }

        // ৪. সময়ভিত্তিক ফিল্টার
        if ($filterDTO->filter === 'today') {
            $query->whereDate('due_date', now()->toDateString());
        } elseif ($filterDTO->filter === 'overdue') {
            $query->whereNull('completed_at')
                  ->where('due_date', '<', now());
        }

        // ৫. লারাভেলের পেজিনেটর থেকে ডেটা আনা
        $eloquentPaginator = $query->orderBy('due_date', 'asc')->paginate($filterDTO->perPage);

        // ৬. ডাটাবেসের মডেলগুলোকে ডোমেইন এন্টিটি (ActivityEntity)-তে রূপান্তর করা
        $entities = collect($eloquentPaginator->items())
            ->map(fn(Activity $item) => $this->mapper->toEntity($item->toArray()))
            ->all();

        // ৭. আপনার ডোমেইনের নিজস্ব PaginatedResult রিটার্ন করা
        return new PaginatedResult(
            items: $entities,
            total: $eloquentPaginator->total(),
            currentPage: $eloquentPaginator->currentPage(),
            perPage: $eloquentPaginator->perPage(),
            lastPage: $eloquentPaginator->lastPage(),
        );
    }



    public function findById(int $id): ?ActivityEntity
    {
        $activityModel = Activity::with(['user', 'trackable'])->find($id);

        if (! $activityModel) {
            return null;
        }

        return $this->mapper->toEntity($activityModel->toArray());
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
     * @param int                $id
     * @param ActivityUpdateData $activityUpdateData
     *
     * @return ActivityEntity|null Null when the activity no longer exists.
     */
    public function update(int $id, ActivityUpdateData $activityUpdateData): ?ActivityEntity
    {
        $activityModel = $this->findRecord($id);

        if (! $activityModel) {
            return null;
        }

        // Only the mutable columns are written; the tracked record and the
        // owning user are left exactly as they were.
        $activityModel->subject = $activityUpdateData->subject;
        $activityModel->type = $activityUpdateData->type?->value;
        $activityModel->description = $activityUpdateData->description;
        $activityModel->due_date = $activityUpdateData->dueDate;
        $activityModel->completed_at = $activityUpdateData->completedAt;

        $activityModel->save();

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
}