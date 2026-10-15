<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Contracts;

use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;

/**
 * PORT: the Application layer depends on THIS, never on Eloquent.
 */
interface ActivityRepositoryContract
{
    /**
     * Persists the activity. An entity without an ID is inserted, one that
     * already carries an ID is updated in place.
     *
     * @param ActivityEntity $activity
     *
     * @return ActivityEntity
     */
    public function save(ActivityEntity $activity): ActivityEntity;

    /**
     * Returns a paginated list of activities.
     *
     * @param int $perPage Items per page (default: 10).
     *
     * @return PaginatedResult Items are ActivityProjectionDTO instances.
     */
    public function paginate(int $perPage = 10): PaginatedResult;

    /**
     * Returns the activities matching the given filter criteria.
     *
     * @param ActivityFilter $filter
     *
     * @return PaginatedResult Items are ActivityProjectionDTO instances.
     */
    public function getFilteredActivities(ActivityFilter $filter): PaginatedResult;

    /**
     * Finds an activity by its ID, with its relations loaded.
     *
     * @param int $id
     *
     * @return ActivityProjectionDTO|null
     */
    public function findById(int $id): ?ActivityProjectionDTO;

    /**
     * Finds the entity by its ID with a row lock, for use inside a transaction.
     *
     * @param int $id
     *
     * @return ActivityEntity|null
     */
    public function findByIdWithLock(int $id): ?ActivityEntity;

    /**
     * Deletes an activity.
     *
     * @param ActivityEntity $activity
     *
     * @return void
     */
    public function delete(ActivityEntity $activity): void;
}
