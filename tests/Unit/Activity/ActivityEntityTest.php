<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Exceptions\InvalidActivityException;
use Core\Activity\Domain\ValueObjects\TrackableType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ActivityEntity's own behaviour. Pure - no mocks.
 */
final class ActivityEntityTest extends TestCase
{
    public function test_create_new_starts_without_an_id_or_completion(): void
    {
        $entity = ActivityEntity::createNew(
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
        );

        $this->assertNull($entity->getId());
        $this->assertNull($entity->getCompletedAt());
        $this->assertTrue($entity->isPending());
    }

    public function test_create_new_sets_the_completion_time_when_one_is_supplied(): void
    {
        $entity = ActivityEntity::createNew(
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            completedAt: new \DateTimeImmutable('2026-10-04 08:15:00'),
        );

        $this->assertFalse($entity->isPending());
        $this->assertSame('2026-10-04 08:15:00', $entity->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_create_new_rejects_a_future_completion_time(): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('An activity cannot be completed in the future');

        ActivityEntity::createNew(
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            completedAt: new \DateTimeImmutable('2099-01-01 00:00:00'),
        );
    }

    public function test_create_new_accepts_a_completion_time_in_the_past(): void
    {
        $entity = ActivityEntity::createNew(
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            completedAt: new \DateTimeImmutable('2026-10-04 08:15:00'),
        );

        $this->assertFalse($entity->isPending());
        $this->assertSame('2026-10-04 08:15:00', $entity->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_create_new_rejects_an_empty_subject(): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('An activity must carry a subject.');

        ActivityEntity::createNew(
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: '',
        );
    }

    #[DataProvider('nonPositiveTrackableIds')]
    public function test_create_new_rejects_a_non_positive_trackable_id(int $trackableId): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('Trackable ID must be a positive integer');

        ActivityEntity::createNew(
            trackableType: 'App\\Models\\Lead',
            trackableId: $trackableId,
            type: ActivityType::CALL,
            subject: 'Discovery call',
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonPositiveTrackableIds(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function test_create_new_rejects_an_empty_trackable_type(): void
    {
        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('An activity must reference a trackable type.');

        ActivityEntity::createNew(
            trackableType: '',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
        );
    }

    public function test_apply_changes_applies_every_supplied_field(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(
            subject: 'New subject',
            type: ActivityType::MEETING,
            description: 'New description',
            dueDate: new \DateTimeImmutable('2026-12-01 10:00:00'),
        );

        $this->assertSame('New subject', $entity->getSubject());
        $this->assertSame(ActivityType::MEETING, $entity->getType());
        $this->assertSame('New description', $entity->getDescription());
        $this->assertSame('2026-12-01 10:00:00', $entity->getDueDate()?->format('Y-m-d H:i:s'));
    }

    public function test_apply_changes_leaves_null_arguments_unchanged(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(subject: 'Only subject');

        $this->assertSame('Only subject', $entity->getSubject());
        $this->assertSame(ActivityType::CALL, $entity->getType());
        $this->assertSame('Intro call with the prospect', $entity->getDescription());
        $this->assertSame('2026-10-05 09:30:00', $entity->getDueDate()?->format('Y-m-d H:i:s'));
    }

    public function test_apply_changes_rejects_an_empty_subject(): void
    {
        $entity = $this->makeEntity();

        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('An activity must carry a subject.');

        $entity->applyChanges(subject: '');
    }

    public function test_apply_changes_rejects_a_whitespace_only_subject(): void
    {
        $entity = $this->makeEntity();

        $this->expectException(InvalidActivityException::class);

        $entity->applyChanges(subject: '   ');
    }

    public function test_apply_changes_keeps_the_subject_when_the_rejected_update_is_caught(): void
    {
        $entity = $this->makeEntity();

        try {
            $entity->applyChanges(subject: '');
        } catch (InvalidActivityException) {
            // The entity is left exactly as it was.
        }

        $this->assertSame('Discovery call', $entity->getSubject());
    }

    public function test_apply_changes_never_touches_the_tracked_record(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(subject: 'New subject', type: ActivityType::EMAIL);

        $this->assertSame('App\\Models\\Lead', $entity->getTrackableType()->toString());
        $this->assertSame(42, $entity->getTrackableId());
        $this->assertSame(7, $entity->getUserId());
    }

    public function test_mark_completed_sets_the_completion_time(): void
    {
        $entity = $this->makeEntity();

        $entity->markCompleted(new \DateTimeImmutable('2026-10-02 11:00:00'));

        $this->assertFalse($entity->isPending());
        $this->assertSame('2026-10-02 11:00:00', $entity->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_mark_completed_rejects_an_already_completed_activity(): void
    {
        $entity = $this->makeEntity();
        $entity->markCompleted(new \DateTimeImmutable('2026-10-02 11:00:00'));

        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('The activity is already completed.');

        $entity->markCompleted(new \DateTimeImmutable('2026-10-03 11:00:00'));
    }

    public function test_mark_completed_rejects_a_future_completion_time(): void
    {
        $entity = $this->makeEntity();

        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('An activity cannot be completed in the future');

        $entity->markCompleted(
            completedAt: new \DateTimeImmutable('2026-12-02 11:00:00'),
            now: new \DateTimeImmutable('2026-12-01 09:00:00'),
        );
    }

    public function test_mark_completed_accepts_the_current_moment(): void
    {
        $entity = $this->makeEntity();
        $now = new \DateTimeImmutable('2026-10-02 11:00:00');

        $entity->markCompleted(completedAt: $now, now: $now);

        $this->assertSame($now, $entity->getCompletedAt());
    }

    public function test_reopen_clears_the_completion_time(): void
    {
        $entity = $this->makeCompletedEntity();

        $entity->reopen();

        $this->assertTrue($entity->isPending());
        $this->assertNull($entity->getCompletedAt());
    }

    /**
     * Called directly, a reopen of a pending activity hides a caller mistake,
     * so it is refused. applyChanges() deliberately does not share this
     * strictness, because a payload may carry the flag unconditionally.
     */
    public function test_reopen_rejects_an_activity_that_is_already_pending(): void
    {
        $entity = $this->makeEntity();

        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('The activity is not completed, so it cannot be reopened.');

        $entity->reopen();
    }

    public function test_reopen_leaves_the_pending_activity_untouched_when_refused(): void
    {
        $entity = $this->makeEntity();

        try {
            $entity->reopen();
        } catch (InvalidActivityException) {
            // Refused; the activity must still be pending and unmodified.
        }

        $this->assertTrue($entity->isPending());
        $this->assertNull($entity->getCompletedAt());
    }

    public function test_clear_due_date_drops_the_due_date(): void
    {
        $entity = $this->makeEntity();

        $entity->clearDueDate();

        $this->assertNull($entity->getDueDate());
    }

    public function test_clear_description_drops_the_description(): void
    {
        $entity = $this->makeEntity();

        $entity->clearDescription();

        $this->assertNull($entity->getDescription());
    }

    /**
     * A null due date means "leave unchanged", so clearing needs its own flag.
     */
    public function test_apply_changes_clears_the_due_date_when_asked(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(subject: 'New subject', clearDueDate: true);

        $this->assertNull($entity->getDueDate());
        $this->assertSame('New subject', $entity->getSubject());
        // The description is untouched by a due-date clear.
        $this->assertSame('Intro call with the prospect', $entity->getDescription());
    }

    public function test_apply_changes_clears_the_description_when_asked(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(clearDescription: true);

        $this->assertNull($entity->getDescription());
        // The due date is untouched by a description clear.
        $this->assertSame('2026-10-05 09:30:00', $entity->getDueDate()?->format('Y-m-d H:i:s'));
    }

    public function test_apply_changes_clears_both_nullable_fields_at_once(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(clearDueDate: true, clearDescription: true);

        $this->assertNull($entity->getDueDate());
        $this->assertNull($entity->getDescription());
    }

    public function test_apply_changes_rejects_replacing_and_clearing_the_due_date_together(): void
    {
        $entity = $this->makeEntity();

        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('replaced and cleared by the same update');

        $entity->applyChanges(
            dueDate: new \DateTimeImmutable('2026-12-01 10:00:00'),
            clearDueDate: true,
        );
    }

    public function test_apply_changes_leaves_the_activity_untouched_when_a_clear_conflicts(): void
    {
        $entity = $this->makeEntity();

        try {
            $entity->applyChanges(
                subject: 'Should not be applied',
                dueDate: new \DateTimeImmutable('2026-12-01 10:00:00'),
                clearDueDate: true,
            );
        } catch (InvalidActivityException) {
            // Rejected as a whole; nothing below may have been mutated.
        }

        $this->assertSame('Discovery call', $entity->getSubject());
        $this->assertSame('2026-10-05 09:30:00', $entity->getDueDate()?->format('Y-m-d H:i:s'));
    }

    public function test_is_pending_reflects_the_completion_marker(): void
    {
        $entity = $this->makeEntity();

        $this->assertTrue($entity->isPending());

        $entity->markCompleted(new \DateTimeImmutable('2026-10-02 11:00:00'));

        $this->assertFalse($entity->isPending());
    }

    public function test_trackable_type_is_exposed_as_a_value_object(): void
    {
        $entity = $this->makeEntity();

        $this->assertInstanceOf(TrackableType::class, $entity->getTrackableType());
        $this->assertSame('App\\Models\\Lead', $entity->getTrackableType()->toString());
    }

    // ---------------------------------------------------------------------
    // applyChanges()
    // ---------------------------------------------------------------------

    public function test_apply_changes_updates_only_the_supplied_details(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(subject: 'New subject', type: ActivityType::MEETING);

        $this->assertSame('New subject', $entity->getSubject());
        $this->assertSame(ActivityType::MEETING, $entity->getType());
        // Null arguments stay unchanged.
        $this->assertSame('Intro call with the prospect', $entity->getDescription());
        $this->assertSame('2026-10-05 09:30:00', $entity->getDueDate()?->format('Y-m-d H:i:s'));
        $this->assertTrue($entity->isPending());
    }

    public function test_apply_changes_completes_a_pending_activity(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(
            subject: 'New subject',
            completedAt: new \DateTimeImmutable('2026-10-02 11:00:00'),
        );

        $this->assertFalse($entity->isPending());
        $this->assertSame('2026-10-02 11:00:00', $entity->getCompletedAt()?->format('Y-m-d H:i:s'));
        $this->assertSame('New subject', $entity->getSubject());
    }

    /**
     * A completed activity is only reachable through a persisted row: the
     * update path reads one back under the lock, so that is the state a
     * reopen payload actually sees.
     */
    public function test_apply_changes_reopens_a_completed_activity(): void
    {
        $entity = $this->makeCompletedEntity();

        $entity->applyChanges(subject: 'New subject', reopen: true);

        $this->assertTrue($entity->isPending());
        $this->assertNull($entity->getCompletedAt());
        $this->assertSame('New subject', $entity->getSubject());
    }

    /**
     * Client screens send the reopen flag with whatever else changed, so it
     * has to survive an already-pending activity instead of failing the update.
     */
    public function test_apply_changes_treats_reopening_a_pending_activity_as_a_no_op(): void
    {
        $entity = $this->makeEntity();

        $entity->applyChanges(subject: 'New subject', reopen: true);

        $this->assertTrue($entity->isPending());
        $this->assertNull($entity->getCompletedAt());
        $this->assertSame('New subject', $entity->getSubject());
    }

    public function test_apply_changes_rejects_a_payload_that_both_completes_and_reopens(): void
    {
        $entity = $this->makeEntity();

        $this->expectException(InvalidActivityException::class);
        $this->expectExceptionMessage('cannot be completed and reopened by the same update');

        $entity->applyChanges(
            completedAt: new \DateTimeImmutable('2026-10-03 11:00:00'),
            reopen: true,
        );
    }

    public function test_apply_changes_leaves_the_activity_untouched_when_the_payload_conflicts(): void
    {
        $entity = $this->makeEntity();

        try {
            $entity->applyChanges(
                subject: 'Should not be applied',
                completedAt: new \DateTimeImmutable('2026-10-03 11:00:00'),
                reopen: true,
            );
        } catch (InvalidActivityException) {
            // Rejected as a whole; nothing below may have been mutated.
        }

        $this->assertTrue($entity->isPending());
        $this->assertNull($entity->getCompletedAt());
        $this->assertSame('Discovery call', $entity->getSubject());
    }

    public function test_apply_changes_leaves_the_activity_untouched_when_the_subject_is_invalid(): void
    {
        $entity = $this->makeCompletedEntity();

        try {
            // The completion would be legal on its own; the blank subject is
            // what rejects the payload, and it must reject it before any state
            // transition has run.
            $entity->applyChanges(
                subject: '',
                type: ActivityType::MEETING,
                reopen: true,
            );
        } catch (InvalidActivityException) {
            // Rejected before mutation.
        }

        $this->assertFalse($entity->isPending());
        $this->assertSame('2026-10-02 11:00:00', $entity->getCompletedAt()?->format('Y-m-d H:i:s'));
        $this->assertSame(ActivityType::CALL, $entity->getType());
    }

    public function test_apply_changes_keeps_the_completion_when_nothing_is_supplied(): void
    {
        $entity = $this->makeEntity();
        $entity->markCompleted(new \DateTimeImmutable('2026-10-02 11:00:00'));

        $entity->applyChanges(subject: 'New subject');

        $this->assertFalse($entity->isPending());
        $this->assertSame('2026-10-02 11:00:00', $entity->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_apply_changes_rejects_a_future_completion_time(): void
    {
        $entity = $this->makeEntity();

        $this->expectException(InvalidActivityException::class);

        $entity->applyChanges(completedAt: new \DateTimeImmutable('2099-01-01 00:00:00'));
    }

    // ---------------------------------------------------------------------
    // isOverdue()
    // ---------------------------------------------------------------------

    public function test_is_overdue_is_true_for_a_pending_activity_past_its_due_date(): void
    {
        $entity = $this->makeEntity();

        $this->assertTrue($entity->isOverdue(new \DateTimeImmutable('2026-10-06 09:30:00')));
    }

    public function test_is_overdue_is_false_when_the_due_date_lies_ahead(): void
    {
        $entity = $this->makeEntity();

        $this->assertFalse($entity->isOverdue(new \DateTimeImmutable('2026-10-04 09:30:00')));
    }

    public function test_is_overdue_is_false_once_the_activity_is_completed(): void
    {
        $entity = $this->makeEntity();
        $entity->markCompleted(new \DateTimeImmutable('2026-10-02 11:00:00'));

        $this->assertFalse($entity->isOverdue(new \DateTimeImmutable('2026-10-06 09:30:00')));
    }

    public function test_is_overdue_is_false_without_a_due_date(): void
    {
        $entity = ActivityEntity::reconstitute(
            id: 1,
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
        );

        $this->assertFalse($entity->isOverdue(new \DateTimeImmutable('2026-10-06 09:30:00')));
    }

    private function makeEntity(): ActivityEntity
    {
        return ActivityEntity::reconstitute(
            id: 1,
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: new \DateTimeImmutable('2026-10-05 09:30:00'),
            completedAt: null,
        );
    }

    /**
     * Builds an already-completed activity, which is what a reopen payload
     * finds on a persisted row.
     */
    private function makeCompletedEntity(): ActivityEntity
    {
        return ActivityEntity::reconstitute(
            id: 1,
            trackableType: 'App\\Models\\Lead',
            trackableId: 42,
            type: ActivityType::CALL,
            subject: 'Discovery call',
            userId: 7,
            description: 'Intro call with the prospect',
            dueDate: new \DateTimeImmutable('2026-10-05 09:30:00'),
            completedAt: new \DateTimeImmutable('2026-10-02 11:00:00'),
        );
    }
}
