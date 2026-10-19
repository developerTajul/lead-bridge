<?php

declare(strict_types=1);

namespace Tests\Unit\Lead;

use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\Events\LeadConverted;
use Core\Lead\Domain\Exceptions\InvalidLeadException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Covers the lead entity returning a converted copy of itself.
 */
final class LeadConvertedTransitionTest extends TestCase
{
    private function makeLead(LeadStatus $status = LeadStatus::QUALIFIED): LeadEntity
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

    public function test_it_returns_a_new_instance_rather_than_mutating_the_original(): void
    {
        $lead = $this->makeLead();
        $converted = $lead->converted();

        $this->assertNotSame($lead, $converted);
        $this->assertSame(LeadStatus::CONVERTED, $converted->getStatus());
        $this->assertSame(LeadStatus::QUALIFIED, $lead->getStatus());
    }

    public function test_it_keeps_the_lead_details_on_the_converted_copy(): void
    {
        $lead = $this->makeLead();
        $converted = $lead->converted();

        $this->assertSame($lead->id, $converted->id);
        $this->assertSame($lead->name, $converted->name);
        $this->assertSame($lead->email, $converted->email);
        $this->assertSame($lead->phone, $converted->phone);
        $this->assertSame($lead->companyName, $converted->companyName);
        $this->assertSame($lead->source, $converted->source);
    }

    /**
     * A lead is only convertible from qualified, so every other status must be
     * refused without the copy being built.
     */
    public static function nonConvertibleStatuses(): array
    {
        return [
            'new' => [LeadStatus::NEW],
            'contacted' => [LeadStatus::CONTACTED],
            'lost' => [LeadStatus::LOST],
            'already converted' => [LeadStatus::CONVERTED],
        ];
    }

    #[DataProvider('nonConvertibleStatuses')]
    public function test_it_refuses_a_lead_that_is_not_qualified(LeadStatus $status): void
    {
        $this->expectException(InvalidLeadException::class);

        $this->makeLead($status)->converted();
    }

    public function test_the_refused_lead_keeps_its_original_status(): void
    {
        $lead = $this->makeLead(LeadStatus::NEW);

        try {
            $lead->converted();
        } catch (InvalidLeadException) {
            // Expected: the assertion below is the point of the test.
        }

        $this->assertSame(LeadStatus::NEW, $lead->getStatus());
    }

    public function test_the_converted_event_is_readonly_and_carries_scalar_details(): void
    {
        $occurredAt = new DateTimeImmutable('2026-10-19 12:00:00');

        $event = new LeadConverted(
            leadId: 7,
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            phone: '555-0100',
            companyName: 'Analytical Engines',
            source: 'web',
            occurredAt: $occurredAt,
        );

        $this->assertSame(7, $event->leadId);
        $this->assertSame('Ada Lovelace', $event->name);
        $this->assertSame('ada@example.com', $event->email);
        $this->assertSame('555-0100', $event->phone);
        $this->assertSame('Analytical Engines', $event->companyName);
        $this->assertSame('web', $event->source);
        $this->assertSame($occurredAt, $event->occurredAt);

        $this->assertTrue((new ReflectionClass($event))->isReadOnly());
    }

    public function test_it_tolerates_a_lead_without_email_phone_or_company(): void
    {
        $lead = new LeadEntity(
            id: 2,
            name: 'Bare Prospect',
            email: null,
            phone: null,
            companyName: null,
            status: LeadStatus::QUALIFIED,
            source: 'import',
            createdAt: '2026-10-05 10:13:16',
            updatedAt: '2026-10-05 10:13:16',
        );

        $converted = $lead->converted();

        $this->assertSame(LeadStatus::CONVERTED, $converted->getStatus());
        $this->assertNull($converted->email);
        $this->assertNull($converted->phone);
        $this->assertNull($converted->companyName);
    }
}
