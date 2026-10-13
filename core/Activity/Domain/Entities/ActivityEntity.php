<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Entities;

use Core\Activity\Domain\Enums\ActivityType;

/**
 * Pure domain entity representing an activity.
 */
readonly class ActivityEntity
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public string $trackableType,
        public int $trackableId,
        public ActivityType $type,
        public string $subject,
        public ?string $description,
        public ?string $dueDate,
        public ?string $completedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}