<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Events;

use Core\Activity\Domain\Entities\ActivityEntity;

/**
 * Event fired when a new activity is successfully created.
 */
readonly class ActivityCreatedEvent
{
    public function __construct(
        public int $activityId,
        public string $trackableType,
        public int $trackableId
    ) {}
}