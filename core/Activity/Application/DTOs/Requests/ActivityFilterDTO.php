<?php

declare(strict_types=1);

namespace Core\Activity\Application\DTOs\Requests;

use Core\Activity\Domain\Enums\ActivityPeriod;
use Core\Activity\Domain\Enums\ActivityStatus;
use Core\Activity\Domain\Enums\ActivityType;

/**
 * Carries the raw activity list criteria INTO the Service. Immutable.
 * Built exclusively by the module's Request Mapper - no factory methods here.
 */
readonly class ActivityFilterDTO
{
    public function __construct(
        public ?ActivityStatus $status = null,
        public ?ActivityType $type = null,
        public ?int $userId = null,
        public ?ActivityPeriod $filter = null,
        public int $perPage = 15
    ) {}
}
