<?php

declare(strict_types=1);

namespace Tests\Unit\Activity;

use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;
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

    public function test_update_details_applies_every_supplied_field(): void
    {
        $entity = $this->makeEntity();

        $entity->updateDetails(
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

    public function test_update_details_leaves_null_arguments_unchanged(): void
    {
        $entity = $this->makeEntity();

        $entity->updateDetails(subject: 'Only subject');

        $this->assertSame('Only subject', $entity->getSubject());
        $this->assertSame(ActivityType::CALL, $entity->getType());
        $this->assertSame('Intro call with the prospect', $entity->getDescription());
        $this->assertSame('2026-10-05 09:30:00', $entity->getDueDate()?->format('Y-m-d H:i:s'));
    }

    public function test_update_details_ignores_an_empty_subject(): void
    {
        $entity = $this->makeEntity();

        $entity->updateDetails(subject: '');

        // An activity must always carry a subject, so the guard keeps the old one.
        $this->assertSame('Discovery call', $entity->getSubject());
    }

    public function test_update_details_never_touches_the_tracked_record(): void
    {
        $entity = $this->makeEntity();

        $entity->updateDetails(subject: 'New subject', type: ActivityType::EMAIL);

        $this->assertSame('App\\Models\\Lead', $entity->getTrackableType());
        $this->assertSame(42, $entity->getTrackableId());
        $this->assertSame(7, $entity->getUserId());
    }

    public function test_mark_completed_sets_the_completion_time(): void
    {
        $entity = $this->makeEntity();

        $entity->markCompleted(new \DateTimeImmutable('2026-12-02 11:00:00'));

        $this->assertFalse($entity->isPending());
        $this->assertSame('2026-12-02 11:00:00', $entity->getCompletedAt()?->format('Y-m-d H:i:s'));
    }

    public function test_reopen_clears_the_completion_time(): void
    {
        $entity = $this->makeEntity();
        $entity->markCompleted(new \DateTimeImmutable('2026-12-02 11:00:00'));

        $entity->reopen();

        $this->assertTrue($entity->isPending());
        $this->assertNull($entity->getCompletedAt());
    }

    public function test_is_pending_reflects_the_completion_marker(): void
    {
        $entity = $this->makeEntity();

        $this->assertTrue($entity->isPending());

        $entity->markCompleted(new \DateTimeImmutable('2026-12-02 11:00:00'));

        $this->assertFalse($entity->isPending());
    }

    public function test_with_projection_returns_a_new_instance_and_keeps_the_fields(): void
    {
        $entity = $this->makeEntity();

        $projected = $entity->withProjection(
            user: ['id' => 7, 'name' => 'Tajul'],
            trackable: ['id' => 42, 'name' => 'Acme'],
            createdAt: new \DateTimeImmutable('2026-10-03 10:00:00'),
            updatedAt: new \DateTimeImmutable('2026-10-04 10:00:00'),
        );

        $this->assertNotSame($entity, $projected);
        $this->assertNull($entity->getUser());
        $this->assertSame(['id' => 7, 'name' => 'Tajul'], $projected->getUser());
        $this->assertSame(['id' => 42, 'name' => 'Acme'], $projected->getTrackable());
        $this->assertSame('2026-10-03 10:00:00', $projected->getCreatedAt()?->format('Y-m-d H:i:s'));
        $this->assertSame('Discovery call', $projected->getSubject());
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
}
