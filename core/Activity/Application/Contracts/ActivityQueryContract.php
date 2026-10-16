<?php

declare(strict_types=1);

namespace Core\Activity\Application\Contracts;

use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\ValueObjects\ActivityFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;

/**
 * Read side of the activity persistence port. Lives in the Application layer
 * because it returns ActivityProjectionDTO, keeping the Domain contract free
 * of Application types.
 */
interface ActivityQueryContract
{
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
}
