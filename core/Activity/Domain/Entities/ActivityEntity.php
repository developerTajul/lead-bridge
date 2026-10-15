<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Entities;

use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Exceptions\InvalidActivityException;
use Core\Activity\Domain\ValueObjects\TrackableType;
use DateTimeImmutable;

/**
 * Domain entity representing an activity.
 */
final class ActivityEntity
{
    private function __construct(
        private readonly ?int $id,
        private readonly TrackableType $trackableType,
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
     * @param DateTimeImmutable|null $completedAt
     *
     * @return self
     *
     * @throws InvalidActivityException When the subject or the tracked record is missing.
     */
    public static function createNew(
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
        if (trim($subject) === '') {
            throw InvalidActivityException::emptySubject();
        }

        if ($trackableId <= 0) {
            throw InvalidActivityException::invalidTrackableId($trackableId);
        }

        return new self(
            id: null,
            trackableType: TrackableType::fromString($trackableType),
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
            trackableType: TrackableType::fromString($trackableType),
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
     * are applied; null means "leave unchanged".
     *
     * @param string|null            $subject
     * @param ActivityType|null      $type
     * @param string|null            $description
     * @param DateTimeImmutable|null $dueDate
     *
     * @return void
     *
     * @throws InvalidActivityException When an empty subject is supplied.
     */
    public function updateDetails(
        ?string $subject = null,
        ?ActivityType $type = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null
    ): void {
        if ($subject !== null && trim($subject) === '') {
            throw InvalidActivityException::emptySubject();
        }

        if ($subject !== null) {
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
     * Applies an update payload. Null fields stay unchanged; a completion
     * time marks the activity completed, and the reopen flag clears an
     * existing completion.
     *
     * @param string|null            $subject
     * @param ActivityType|null      $type
     * @param string|null            $description
     * @param DateTimeImmutable|null $dueDate
     * @param DateTimeImmutable|null $completedAt
     * @param bool                   $reopen
     *
     * @return void
     *
     * @throws InvalidActivityException When a change would break an invariant.
     */
    public function applyChanges(
        ?string $subject = null,
        ?ActivityType $type = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null,
        ?DateTimeImmutable $completedAt = null,
        bool $reopen = false
    ): void {
        if ($reopen) {
            $this->reopen();
        } elseif ($completedAt !== null) {
            $this->markCompleted($completedAt);
        }

        $this->updateDetails(
            subject: $subject,
            type: $type,
            description: $description,
            dueDate: $dueDate,
        );
    }

    /**
     * Marks the activity as completed at the given moment.
     *
     * @param DateTimeImmutable      $completedAt
     * @param DateTimeImmutable|null $now Clock to compare against; defaults to the current time.
     *
     * @return void
     *
     * @throws InvalidActivityException When the activity is already completed or the moment lies ahead.
     */
    public function markCompleted(DateTimeImmutable $completedAt, ?DateTimeImmutable $now = null): void
    {
        if (! $this->isPending()) {
            throw InvalidActivityException::alreadyCompleted();
        }

        $now ??= new DateTimeImmutable();

        if ($completedAt > $now) {
            throw InvalidActivityException::completionInTheFuture($completedAt, $now);
        }

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

    /**
     * Whether the activity is still pending past its due date.
     *
     * @param DateTimeImmutable $now
     *
     * @return bool
     */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        if ($this->dueDate === null) {
            return false;
        }

        return $this->dueDate < $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrackableType(): TrackableType
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
}
