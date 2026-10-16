<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Entities;

use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Events\ActivityCreatedEvent;
use Core\Activity\Domain\Events\ActivityDeletedEvent;
use Core\Activity\Domain\Exceptions\InvalidActivityException;
use Core\Activity\Domain\ValueObjects\TrackableType;
use DateTimeImmutable;
use LogicException;

/**
 * Domain entity representing an activity.
 */
final class ActivityEntity
{
    /** @var array<int, object> */
    private array $recordedEvents = [];

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
     * @param DateTimeImmutable|null $now Clock to compare against; defaults to the current time.
     *
     * @return self
     *
     * @throws InvalidActivityException When the subject, the tracked record or the completion time is invalid.
     */
    public static function createNew(
        string $trackableType,
        int $trackableId,
        ActivityType $type,
        string $subject,
        ?int $userId = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null,
        ?DateTimeImmutable $completedAt = null,
        ?DateTimeImmutable $now = null
    ): self
    {
        if (trim($subject) === '') {
            throw InvalidActivityException::emptySubject();
        }

        if ($trackableId <= 0) {
            throw InvalidActivityException::invalidTrackableId($trackableId);
        }

        if ($completedAt !== null) {
            self::assertCompletable($completedAt, $now);
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
     * Records that this activity has been persisted, so listeners react to
     * the stored activity rather than one that was rejected.
     *
     * @return void
     *
     * @throws LogicException When the activity has not been persisted yet.
     */
    public function recordCreated(): void
    {
        $this->record(new ActivityCreatedEvent(
            activityId: $this->requireId(),
            trackableType: $this->trackableType->toString(),
            trackableId: $this->trackableId,
        ));
    }

    /**
     * Records that this activity is about to be removed.
     *
     * @return void
     *
     * @throws LogicException When the activity has not been persisted yet.
     */
    public function recordDeleted(): void
    {
        $this->record(new ActivityDeletedEvent(
            activityId: $this->requireId(),
            trackableType: $this->trackableType->toString(),
            trackableId: $this->trackableId,
        ));
    }

    /**
     * Returns the persisted ID, refusing to build an event around a null one.
     *
     * @return int
     *
     * @throws LogicException When the activity has no ID.
     */
    private function requireId(): int
    {
        if ($this->id === null) {
            throw new LogicException('Cannot record event for an entity without an ID.');
        }

        return $this->id;
    }

    /**
     * Returns the events recorded since the last release, clearing them.
     *
     * @return array<int, object>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;

        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Records an event raised by this activity.
     *
     * @param object $event
     *
     * @return void
     */
    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
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
     * are applied; null means "leave unchanged". A clear flag drops the field
     * instead. Reached through applyChanges(), which is the single entry point
     * for mutation.
     *
     * @param string|null            $subject
     * @param ActivityType|null      $type
     * @param string|null            $description
     * @param DateTimeImmutable|null $dueDate
     * @param bool                   $clearDueDate
     * @param bool                   $clearDescription
     *
     * @return void
     *
     * @throws InvalidActivityException When an empty subject is supplied.
     */
    private function updateDetails(
        ?string $subject = null,
        ?ActivityType $type = null,
        ?string $description = null,
        ?DateTimeImmutable $dueDate = null,
        bool $clearDueDate = false,
        bool $clearDescription = false
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

        if ($clearDescription) {
            $this->clearDescription();
        } elseif ($description !== null) {
            $this->description = $description;
        }

        if ($clearDueDate) {
            $this->clearDueDate();
        } elseif ($dueDate !== null) {
            $this->dueDate = $dueDate;
        }
    }

    /**
     * Applies an update payload. A null field stays unchanged, a clear flag
     * drops it, completedAt completes the activity and reopen clears an
     * existing completion. The whole payload is validated up front, so a
     * rejected update leaves the activity untouched.
     *
     * @param string|null            $subject
     * @param ActivityType|null      $type
     * @param string|null            $description
     * @param DateTimeImmutable|null $dueDate
     * @param DateTimeImmutable|null $completedAt
     * @param bool                   $reopen           Clear an existing completion.
     * @param bool                   $clearDueDate     Drop the due date.
     * @param bool                   $clearDescription Drop the description.
     * @param DateTimeImmutable|null $now Clock to compare against; defaults to the current time.
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
        bool $reopen = false,
        bool $clearDueDate = false,
        bool $clearDescription = false,
        ?DateTimeImmutable $now = null
    ): void {
        $this->assertUpdateIsApplicable($subject, $dueDate, $completedAt, $reopen, $clearDueDate);

        $this->updateDetails(
            subject: $subject,
            type: $type,
            description: $description,
            dueDate: $dueDate,
            clearDueDate: $clearDueDate,
            clearDescription: $clearDescription,
        );

        if ($reopen) {
            // A payload asking to reopen an activity that is already pending is
            // a no-op, not a contradiction: it carries no completion time, so
            // the end state is the one the caller asked for. reopen() itself
            // stays strict for direct callers.
            if (! $this->isPending()) {
                $this->reopen();
            }

            return;
        }

        if ($completedAt !== null) {
            $this->markCompleted($completedAt, $now);
        }
    }

    /**
     * Checks an update payload before any of it is applied. The completion
     * time is only checked for its own contradiction here; markCompleted()
     * is what asserts it is actually completable.
     *
     * @param string|null            $subject
     * @param DateTimeImmutable|null $dueDate
     * @param DateTimeImmutable|null $completedAt
     * @param bool                   $reopen
     * @param bool                   $clearDueDate
     *
     * @return void
     *
     * @throws InvalidActivityException When the payload is contradictory.
     */
    private function assertUpdateIsApplicable(
        ?string $subject,
        ?DateTimeImmutable $dueDate,
        ?DateTimeImmutable $completedAt,
        bool $reopen,
        bool $clearDueDate
    ): void {
        if ($subject !== null && trim($subject) === '') {
            throw InvalidActivityException::emptySubject();
        }

        // Reopening clears the completion, so asking for both is contradictory.
        if ($reopen && $completedAt !== null) {
            throw InvalidActivityException::conflictingCompletionAndReopen();
        }

        // A replacement and a clear for the same field contradict each other.
        if ($clearDueDate && $dueDate !== null) {
            throw InvalidActivityException::conflictingDueDateAndClear();
        }
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

        self::assertCompletable($completedAt, $now);

        $this->completedAt = $completedAt;
    }

    /**
     * Guards the completion invariant: an activity cannot be completed at a
     * moment that lies ahead of now.
     *
     * @param DateTimeImmutable      $completedAt
     * @param DateTimeImmutable|null $now Clock to compare against; defaults to the current time.
     *
     * @return void
     *
     * @throws InvalidActivityException When the moment lies ahead.
     */
    private static function assertCompletable(DateTimeImmutable $completedAt, ?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable();

        if ($completedAt > $now) {
            throw InvalidActivityException::completionInTheFuture($completedAt, $now);
        }
    }

    /**
     * Clears the completion marker, putting the activity back to pending.
     *
     * @return void
     *
     * @throws InvalidActivityException When the activity is already pending.
     */
    public function reopen(): void
    {
        if ($this->isPending()) {
            throw InvalidActivityException::notCompleted();
        }

        $this->completedAt = null;
    }

    /**
     * Drops the due date.
     *
     * @return void
     */
    public function clearDueDate(): void
    {
        $this->dueDate = null;
    }

    /**
     * Drops the description.
     *
     * @return void
     */
    public function clearDescription(): void
    {
        $this->description = null;
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
