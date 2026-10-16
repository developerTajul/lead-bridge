<?php

declare(strict_types=1);

namespace Core\Activity\Domain\ValueObjects;

use Core\Activity\Domain\Enums\ActivityPeriod;
use Core\Activity\Domain\Enums\ActivityStatus;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Exceptions\InvalidActivityException;

/**
 * Filter criteria the repository uses to narrow down the activity list.
 * Validates itself, so the criteria reaching Infrastructure are always usable.
 */
final readonly class ActivityFilter
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param ActivityStatus|null $status
     * @param ActivityType|null   $type
     * @param int|null            $userId
     * @param ActivityPeriod|null $period
     * @param int                 $perPage
     *
     * @throws InvalidActivityException When the page size falls outside its bounds.
     */
    public function __construct(
        public ?ActivityStatus $status = null,
        public ?ActivityType $type = null,
        public ?int $userId = null,
        public ?ActivityPeriod $period = null,
        public int $perPage = 15,
    ) {
        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw InvalidActivityException::invalidPerPage($perPage);
        }
    }
}
