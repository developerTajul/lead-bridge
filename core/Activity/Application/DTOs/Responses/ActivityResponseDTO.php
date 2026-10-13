<?php

declare(strict_types=1);

namespace Core\Activity\Application\DTOs\Responses;

/**
 * Shape returned to the delivery layer. Never expose an Entity directly
 * to a View/JSON.
 */
readonly class ActivityResponseDTO
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public string $trackableType,
        public int $trackableId,
        public string $type,
        public string $subject,
        public ?string $description,
        public ?string $dueDate,
        public ?string $completedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}