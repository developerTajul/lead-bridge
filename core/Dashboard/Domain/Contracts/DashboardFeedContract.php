<?php

declare(strict_types=1);

namespace Core\Dashboard\Domain\Contracts;

use Core\Dashboard\Domain\DataObjects\ActivityFeedEntry;

/**
 * Read side of the dashboard's activity feed port: the activities still due,
 * soonest first, with the record each was logged against already resolved.
 */
interface DashboardFeedContract
{
    /**
     * Returns the outstanding activities the dashboard lists.
     *
     * @param int $limit How many rows to return at most.
     *
     * @return array<int, ActivityFeedEntry> Empty when nothing is outstanding.
     */
    public function getUpcomingActivities(int $limit): array;
}
