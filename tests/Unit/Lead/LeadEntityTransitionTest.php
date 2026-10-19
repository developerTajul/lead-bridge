<?php

declare(strict_types=1);

namespace Tests\Unit\Lead;

use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\Exceptions\InvalidLeadException;
use ReflectionClass;
use Tests\TestCase;

/**
 * Covers the lead entity guarding its own status.
 */
final class LeadEntityTransitionTest extends TestCase
{
    private function makeLead(LeadStatus $status = LeadStatus::NEW): LeadEntity
    {
        return new LeadEntity(
            id: 1,
            name: 'Acme Prospect',
            email: 'prospect@acme.test',
            phone: '555-0100',
            companyName: 'Acme Ltd',
            status: $status,
            source: 'referral',
            createdAt: '2026-10-05 10:13:16',
            updatedAt: '2026-10-05 10:13:16',
        );
    }

    public function test_it_starts_in_the_status_it_was_built_with(): void
    {
        $this->assertSame(LeadStatus::NEW, $this->makeLead()->getStatus());
    }

    public function test_it_moves_through_the_expected_path_to_converted(): void
    {
        $lead = $this->makeLead();

        $lead->markAsContacted();
        $this->assertSame(LeadStatus::CONTACTED, $lead->getStatus());

        $lead->qualify();
        $this->assertSame(LeadStatus::QUALIFIED, $lead->getStatus());

        $lead->convert();
        $this->assertSame(LeadStatus::CONVERTED, $lead->getStatus());
    }

    public function test_it_rejects_qualifying_a_new_lead(): void
    {
        $lead = $this->makeLead();

        $this->expectException(InvalidLeadException::class);

        $lead->qualify();
    }

    public function test_it_rejects_converting_a_contacted_lead(): void
    {
        $lead = $this->makeLead(LeadStatus::CONTACTED);

        $this->expectException(InvalidLeadException::class);

        $lead->convert();
    }

    public function test_it_rejects_losing_a_converted_lead(): void
    {
        $lead = $this->makeLead(LeadStatus::CONVERTED);

        $this->expectException(InvalidLeadException::class);

        $lead->markAsLost();
    }

    /**
     * A repeated action must not report success, so the caller notices the
     * double call rather than believing the status changed twice.
     */
    public function test_it_rejects_a_repeated_transition(): void
    {
        $lead = $this->makeLead(LeadStatus::CONTACTED);

        $this->expectException(InvalidLeadException::class);

        $lead->markAsContacted();
    }

    public function test_it_leaves_the_status_alone_when_a_transition_is_rejected(): void
    {
        $lead = $this->makeLead(LeadStatus::NEW);

        try {
            $lead->convert();
        } catch (InvalidLeadException) {
            // Expected: the guard fires before the status is touched.
        }

        $this->assertSame(LeadStatus::NEW, $lead->getStatus());
    }

    public function test_a_lost_lead_can_be_contacted_again(): void
    {
        $lead = $this->makeLead(LeadStatus::LOST);

        $lead->markAsContacted();

        $this->assertSame(LeadStatus::CONTACTED, $lead->getStatus());
    }

    /**
     * The status must not be writable from outside. A public setter would let
     * any caller bypass every rule above, which is what this guards against.
     */
    public function test_it_exposes_no_public_way_to_force_a_status(): void
    {
        $reflection = new ReflectionClass(LeadEntity::class);

        $this->assertFalse(
            $reflection->hasMethod('setState'),
            'LeadEntity must not expose setState(); transitions go through transitionTo().',
        );

        $this->assertFalse(
            $reflection->hasMethod('setStatus'),
            'LeadEntity must not expose setStatus(); transitions go through transitionTo().',
        );

        $statusProperty = $reflection->getProperty('status');

        $this->assertTrue(
            $statusProperty->isPrivate(),
            'The status property must stay private so it only changes through transitionTo().',
        );
    }

    /**
     * The read side still reads these directly, so they must stay public.
     */
    public function test_it_keeps_its_data_fields_publicly_readable(): void
    {
        $lead = $this->makeLead();

        $this->assertSame(1, $lead->id);
        $this->assertSame('Acme Prospect', $lead->name);
        $this->assertSame('prospect@acme.test', $lead->email);
        $this->assertSame('555-0100', $lead->phone);
        $this->assertSame('Acme Ltd', $lead->companyName);
        $this->assertSame('referral', $lead->source);
        $this->assertSame('2026-10-05 10:13:16', $lead->createdAt);
        $this->assertSame('2026-10-05 10:13:16', $lead->updatedAt);
    }
}
