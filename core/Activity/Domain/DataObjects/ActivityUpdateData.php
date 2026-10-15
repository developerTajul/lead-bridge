<?php

declare(strict_types=1);

namespace Core\Activity\Domain\DataObjects;

use Core\Activity\Domain\Enums\ActivityType;

/**
 * Immutable data object for updating an activity. Carries only the fields a
 * caller is allowed to change; trackable_type, trackable_id and user_id are
 * deliberately absent so they cannot be altered through an update.
 */
readonly class ActivityUpdateData
{
    public function __construct(
        public ?string $subject = null,
        public ?ActivityType $type = null,
        public ?string $description = null,
        public ?string $dueDate = null,
        public ?string $completedAt = null,
    ) {}

    /**
     * Returns a new instance with the supplied fields applied. Null arguments
     * mean "leave unchanged".
     *
     * @param string|null            $subject
     * @param ActivityType|null      $type
     * @param string|null            $description
     * @param string|null            $dueDate
     * @param string|null            $completedAt
     *
     * @return self
     */
    public function withChanges(
        ?string $subject = null,
        ?ActivityType $type = null,
        ?string $description = null,
        ?string $dueDate = null,
        ?string $completedAt = null
    ): self {
        return new self(
            subject: $subject ?? $this->subject,
            type: $type ?? $this->type,
            description: $description ?? $this->description,
            dueDate: $dueDate ?? $this->dueDate,
            completedAt: $completedAt ?? $this->completedAt,
        );
    }

    /**
     * Returns the data as an associative array keyed by the activity table's
     * mutable columns only.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'type' => $this->type?->value,
            'description' => $this->description,
            'due_date' => $this->dueDate,
            'completed_at' => $this->completedAt,
        ];
    }
}
