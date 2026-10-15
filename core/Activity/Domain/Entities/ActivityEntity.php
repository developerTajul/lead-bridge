<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Entities;

use Core\Activity\Domain\Enums\ActivityType;
use DateTimeImmutable;

/**
 * Domain entity representing an activity.
 */
final class ActivityEntity
{
    // Relations and timestamps stay read-only: they are projection state, not
    // part of the activity's own business fields.
    private ?array $user = null;
    private ?array $trackable = null;
    private ?DateTimeImmutable $createdAt = null;
    private ?DateTimeImmutable $updatedAt = null;

    private function __construct(
        private readonly ?int $id,
        private readonly string $trackableType,
        private readonly int $trackableId,
        private ActivityType $type,
        private string $subject,
        private readonly ?int $userId = null,
        private ?string $description = null,
        private ?DateTimeImmutable $dueDate = null,
        private ?DateTimeImmutable $completedAt = null
    ) {}

    /**
     * Builds a new, unsaved activity.
     *
     * @param string                 $trackableType
     * @param int                    $trackableId
     * @param ActivityType           $type
     * @param string                 $subject
     * @param int|null               $userId
     * @param string|null            $description
     * @param DateTimeImmutable|null $dueDate
     *
     * @return self
     */
    public static function createNew(
        string $trackableType,
        int $trackableId,
        ActivityType $type,
        string $subject,
        ?int $userId = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null
    ): self
    {
        return new self(
            id: null,
            trackableType: $trackableType,
            trackableId: $trackableId,
            type: $type,
            subject: $subject,
            userId: $userId,
            description: $description,
            dueDate: $dueDate
        );
    }

    /**
     * Rebuilds an activity from persisted state.
     *
     * @param int                    $id
     * @param string                 $trackableType
     * @param int                    $trackableId
     * @param ActivityType           $type
     * @param string                 $subject
     * @param int|null               $userId
     * @param string|null            $description
     * @param DateTimeImmutable|null $dueDate
     * @param DateTimeImmutable|null $completedAt
     *
     * @return self
     */
    public static function reconstitute(
        int $id,
        string $trackableType,
        int $trackableId,
        ActivityType $type,
        string $subject,
        ?int $userId = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null,
        ?DateTimeImmutable $completedAt = null
    ): self
    {
        return new self(
            id: $id,
            trackableType: $trackableType,
            trackableId: $trackableId,
            type: $type,
            subject: $subject,
            userId: $userId,
            description: $description,
            dueDate: $dueDate,
            completedAt: $completedAt
        );
    }

    /**
     * Applies new details to the activity. Only arguments that are not null
     * are applied; null means "leave unchanged". An empty subject is ignored
     * because an activity must always carry one.
     *
     * @param string|null            $subject
     * @param ActivityType|null      $type
     * @param string|null            $description
     * @param DateTimeImmutable|null $dueDate
     *
     * @return void
     */
    public function updateDetails(
        ?string $subject = null,
        ?ActivityType $type = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null
    ): void {
        if ($subject !== null && $subject !== '') {
            $this->subject = $subject;
        }

        if ($type !== null) {
            $this->type = $type;
        }

        if ($description !== null) {
            $this->description = $description;
        }

        if ($dueDate !== null) {
            $this->dueDate = $dueDate;
        }
    }

    /**
     * Marks the activity as completed at the given moment.
     *
     * @param DateTimeImmutable $completedAt
     *
     * @return void
     */
    public function markCompleted(DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    /**
     * Clears the completion marker, putting the activity back to pending.
     *
     * @return void
     */
    public function reopen(): void
    {
        $this->completedAt = null;
    }

    /**
     * Whether the activity is still awaiting completion.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->completedAt === null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrackableType(): string
    {
        return $this->trackableType;
    }

    public function getTrackableId(): int
    {
        return $this->trackableId;
    }

    public function getType(): ActivityType
    {
        return $this->type;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDueDate(): ?DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    public function getTrackable(): ?array
    {
        return $this->trackable;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Returns a new instance carrying the given relations and timestamps.
     * Projection state only - the activity's own fields are untouched.
     *
     * @param array|null             $user
     * @param array|null             $trackable
     * @param DateTimeImmutable|null $createdAt
     * @param DateTimeImmutable|null $updatedAt
     *
     * @return self
     */
    public function withProjection(
        ?array $user = null,
        ?array $trackable = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ): self {
        $clone = clone $this;

        $clone->user = $user;
        $clone->trackable = $trackable;
        $clone->createdAt = $createdAt;
        $clone->updatedAt = $updatedAt;

        return $clone;
    }
}
