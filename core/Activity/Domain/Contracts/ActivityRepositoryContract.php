<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Contracts;

use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Domain\DataObjects\ActivityData;
use Core\Activity\Domain\DataObjects\ActivityUpdateData;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Shared\Domain\DataObjects\PaginatedResult;

/**
 * PORT: the Application layer depends on THIS, never on Eloquent.
 */
interface ActivityRepositoryContract
{
    /**
     * Creates a new activity and returns it as a Domain Entity.
     *
     * @param ActivityData $activityData
     *
     * @return ActivityEntity
     */
    public function create(ActivityData $activityData): ActivityEntity;

    /**
     * Returns a paginated list of activities.
     *
     * @param int $perPage Items per page (default: 10).
     *
     * @return PaginatedResult
     */
    public function paginate(int $perPage = 10): PaginatedResult;

    /**
     * DTO ব্যবহার করে ফিল্টার করা অ্যাক্টিভিটি লিস্ট নিয়ে আসবে
     */
    public function getFilteredActivities(ActivityFilterDTO $filterDTO): PaginatedResult;


    /**
     * Finds an activity by its ID.
     *
     * @param int $id
     * @return ActivityEntity|null
     */
    public function findById(int $id): ?ActivityEntity;

    /**
     * Finds an activity by its ID with a row lock, for use inside a transaction.
     *
     * @param int $id
     *
     * @return ActivityEntity|null
     */
    public function findByIdWithLock(int $id): ?ActivityEntity;

    /**
     * Persists the mutable fields of an activity. The tracked record and the
     * owning user are never written here.
     *
     * @param int                $id
     * @param ActivityUpdateData $activityUpdateData
     *
     * @return ActivityEntity|null Null when the activity no longer exists.
     */
    public function update(int $id, ActivityUpdateData $activityUpdateData): ?ActivityEntity;
}