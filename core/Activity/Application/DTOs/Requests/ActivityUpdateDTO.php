<?php

declare(strict_types=1);

namespace Core\Activity\Application\DTOs\Requests;

use Core\Activity\Domain\Enums\ActivityType;
use DateTimeImmutable;

/**
 * Carries validated activity update input into the Service. Immutable.
 * Built exclusively by ActivityMapper - no factory methods here.
 */
readonly class ActivityUpdateDTO
{
    public function __construct(
        public int $id,
        public ?string $subject = null,
        public ?ActivityType $type = null,
        public ?string $description = null,
        public ?DateTimeImmutable $dueDate = null,
        public ?DateTimeImmutable $completedAt = null,
        public bool $reopen = false,
    ) {}
}
