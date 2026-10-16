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
        ?DateTimeImmutable $completedAt = null
    ): self
    {
        if (trim($subject) === '') {
            throw InvalidActivityException::emptySubject();
        }

        if ($trackableId <= 0) {
            throw InvalidActivityException::invalidTrackableId($trackableId);
        }

        if ($completedAt !== null) {
            self::assertCompletable($completedAt);
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
     * are applied; null means "leave unchanged". Reached through
     * applyChanges(), which is the single entry point for mutation.
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
    private function updateDetails(
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
     * existing completion. Every value is validated before the entity is
     * mutated, so a rejected payload leaves the activity untouched.
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
        $this->assertUpdateIsApplicable($subject, $completedAt, $reopen);

        $this->updateDetails(
            subject: $subject,
            type: $type,
            description: $description,
            dueDate: $dueDate,
        );

        if ($reopen) {
            $this->reopen();

            return;
        }

        if ($completedAt !== null) {
            $this->markCompleted($completedAt);
        }
    }

    /**
     * Checks an update payload before any of it is applied.
     *
     * @param string|null            $subject
     * @param DateTimeImmutable|null $completedAt
     * @param bool                   $reopen
     *
     * @return void
     *
     * @throws InvalidActivityException When the payload is contradictory or breaks an invariant.
     */
    private function assertUpdateIsApplicable(
        ?string $subject,
        ?DateTimeImmutable $completedAt,
        bool $reopen
    ): void {
        if ($subject !== null && trim($subject) === '') {
            throw InvalidActivityException::emptySubject();
        }

        // Reopening clears the completion, so asking for both is contradictory.
        if ($reopen && $completedAt !== null) {
            throw InvalidActivityException::conflictingCompletionAndReopen();
        }

        if ($reopen) {
            return;
        }

        if ($completedAt === null) {
            return;
        }

        if (! $this->isPending()) {
            throw InvalidActivityException::alreadyCompleted();
        }

        self::assertCompletable($completedAt);
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
