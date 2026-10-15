<?php

declare(strict_types=1);

namespace Core\Activity\Domain\ValueObjects;

/**
 * Filter criteria the repository uses to narrow down the activity list.
 */
readonly class ActivityFilter
{
    public function __construct(
        public ?string $status = null,
        public ?string $type = null,
        public ?int $userId = null,
        public ?string $period = null,
        public int $perPage = 15,
    ) {}
}
