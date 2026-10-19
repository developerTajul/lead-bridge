<?php

declare(strict_types=1);

namespace Tests\Unit\Lead;

use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\Exceptions\InvalidLeadException;
use Core\Lead\Domain\LeadTransitions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the allowed status transitions for a lead.
 *
 * Every pair of statuses is checked, so the table cannot silently drift as
 * statuses are added.
 */
final class LeadTransitionsTest extends TestCase
{
    /**
     * Every status pair and whether the move is allowed.
     *
     * @return array<string, array{0: LeadStatus, 1: LeadStatus, 2: bool}>
     */
    public static function transitionProvider(): array
    {
        return [
            // From new.
            'new to contacted' => [LeadStatus::NEW, LeadStatus::CONTACTED, true],
            'new to qualified' => [LeadStatus::NEW, LeadStatus::QUALIFIED, false],
            'new to converted' => [LeadStatus::NEW, LeadStatus::CONVERTED, false],
            'new to lost' => [LeadStatus::NEW, LeadStatus::LOST, true],
            'new to new' => [LeadStatus::NEW, LeadStatus::NEW, false],

            // From contacted. Converting must go through qualified.
            'contacted to qualified' => [LeadStatus::CONTACTED, LeadStatus::QUALIFIED, true],
            'contacted to converted' => [LeadStatus::CONTACTED, LeadStatus::CONVERTED, false],
            'contacted to lost' => [LeadStatus::CONTACTED, LeadStatus::LOST, true],
            'contacted to new' => [LeadStatus::CONTACTED, LeadStatus::NEW, false],
            'contacted to contacted' => [LeadStatus::CONTACTED, LeadStatus::CONTACTED, false],

            // From qualified.
            'qualified to converted' => [LeadStatus::QUALIFIED, LeadStatus::CONVERTED, true],
            'qualified to lost' => [LeadStatus::QUALIFIED, LeadStatus::LOST, true],
            'qualified to contacted' => [LeadStatus::QUALIFIED, LeadStatus::CONTACTED, false],
            'qualified to new' => [LeadStatus::QUALIFIED, LeadStatus::NEW, false],
            'qualified to qualified' => [LeadStatus::QUALIFIED, LeadStatus::QUALIFIED, false],

            // From converted. Converted is terminal.
            'converted to new' => [LeadStatus::CONVERTED, LeadStatus::NEW, false],
            'converted to contacted' => [LeadStatus::CONVERTED, LeadStatus::CONTACTED, false],
            'converted to qualified' => [LeadStatus::CONVERTED, LeadStatus::QUALIFIED, false],
            'converted to lost' => [LeadStatus::CONVERTED, LeadStatus::LOST, false],
            'converted to converted' => [LeadStatus::CONVERTED, LeadStatus::CONVERTED, false],

            // From lost. A lost lead can be picked back up.
            'lost to contacted' => [LeadStatus::LOST, LeadStatus::CONTACTED, true],
            'lost to qualified' => [LeadStatus::LOST, LeadStatus::QUALIFIED, false],
            'lost to converted' => [LeadStatus::LOST, LeadStatus::CONVERTED, false],
            'lost to new' => [LeadStatus::LOST, LeadStatus::NEW, false],
            'lost to lost' => [LeadStatus::LOST, LeadStatus::LOST, false],
        ];
    }

    #[DataProvider('transitionProvider')]
    public function test_it_knows_which_transitions_are_allowed(
        LeadStatus $from,
        LeadStatus $to,
        bool $expected,
    ): void {
        $this->assertSame($expected, LeadTransitions::can($from, $to));
    }

    #[DataProvider('transitionProvider')]
    public function test_it_rejects_exactly_the_disallowed_transitions(
        LeadStatus $from,
        LeadStatus $to,
        bool $expected,
    ): void {
        if ($expected) {
            LeadTransitions::assertCan($from, $to);
            $this->addToAssertionCount(1);

            return;
        }

        $this->expectException(InvalidLeadException::class);

        LeadTransitions::assertCan($from, $to);
    }

    /**
     * Every status must have a row, so adding a case cannot leave a gap.
     */
    public function test_it_covers_every_status(): void
    {
        foreach (LeadStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, LeadTransitions::ALLOWED);
        }
    }

    /**
     * The rule that motivated building the table: a contacted lead has to be
     * qualified before it can convert.
     */
    public function test_a_contacted_lead_cannot_convert_directly(): void
    {
        $this->assertFalse(LeadTransitions::can(LeadStatus::CONTACTED, LeadStatus::CONVERTED));
    }
}
