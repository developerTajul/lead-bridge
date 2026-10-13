<?php

declare(strict_types=1);

namespace Core\Activity\Domain\DataObjects;

use Core\Activity\Domain\Enums\ActivityType;

/**
 * Immutable data object for creating or updating an activity.
 */
readonly class ActivityData
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

    /**
     * Returns the data as an associative array suitable for database insertion/update.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'user_id' => $this->userId,
            'trackable_type' => $this->trackableType,
            'trackable_id' => $this->trackableId,
            'type' => $this->type->value,
            'subject' => $this->subject,
            'description' => $this->description,
            'due_date' => $this->dueDate,
            'completed_at' => $this->completedAt,
        ];

        return array_filter($data, fn($value) => $value !== null);
    }
}