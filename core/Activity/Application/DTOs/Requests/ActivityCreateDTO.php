<?php

declare(strict_types=1);

namespace Core\Activity\Application\DTOs\Requests;

use Core\Activity\Domain\Enums\ActivityType;

/**
 * Carries validated activity creation input into the Service. Immutable.
 * Built exclusively by ActivityMapper - no factory methods here.
 */
readonly class ActivityCreateDTO
{
    public function __construct(
        public string $trackableType,
        public int $trackableId,
        public ActivityType $type,
        public string $subject,
        public ?int $userId = null,
        public ?string $description = null,
        public ?string $dueDate = null,
        public ?string $completedAt = null,
    ) {}
}