<?php

declare(strict_types=1);

namespace Core\Lead\Domain\Entities;

use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\Exceptions\InvalidLeadException;
use Core\Lead\Domain\LeadTransitions;

/**
 * Domain entity for a lead, guarding its own status changes.
 */
final class LeadEntity
{
    /**
     * The lead's status. Not public: it only changes through transitionTo().
     *
     * @var LeadStatus
     */
    private LeadStatus $status;

    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $companyName,
        LeadStatus $status,
        public string $source,
        public string $createdAt,
        public string $updatedAt,
    ) {
        $this->status = $status;
    }

    /**
     * Returns the lead's current status.
     *
     * @return LeadStatus
     */
    public function getStatus(): LeadStatus
    {
        return $this->status;
    }

    /**
     * Moves the lead to a new status, rejecting one the rules do not allow.
     * This is the only way the status changes.
     *
     * @param LeadStatus $to
     *
     * @return void
     *
     * @throws InvalidLeadException When the transition is not allowed.
     */
    public function transitionTo(LeadStatus $to): void
    {
        LeadTransitions::assertCan($this->status, $to);

        $this->status = $to;
    }

    /**
     * Marks the lead as contacted. Allowed from new and lost.
     *
     * @return void
     *
     * @throws InvalidLeadException When the transition is not allowed.
     */
    public function markAsContacted(): void
    {
        $this->transitionTo(LeadStatus::CONTACTED);
    }

    /**
     * Marks the lead as qualified. Only allowed from contacted.
     *
     * @return void
     *
     * @throws InvalidLeadException When the transition is not allowed.
     */
    public function qualify(): void
    {
        $this->transitionTo(LeadStatus::QUALIFIED);
    }

    /**
     * Marks the lead as converted. Only allowed from qualified.
     *
     * @return void
     *
     * @throws InvalidLeadException When the transition is not allowed.
     */
    public function convert(): void
    {
        $this->transitionTo(LeadStatus::CONVERTED);
    }

    /**
     * Returns a converted copy of this lead, leaving the original untouched.
     * The rule is checked before a copy is built, so an ineligible lead never
     * becomes a converted one.
     *
     * @return self
     *
     * @throws InvalidLeadException When the transition is not allowed.
     */
    public function converted(): self
    {
        LeadTransitions::assertCan($this->status, LeadStatus::CONVERTED);

        $converted = clone $this;

        $converted->status = LeadStatus::CONVERTED;

        return $converted;
    }

    /**
     * Marks the lead as lost. Not allowed from converted.
     *
     * @return void
     *
     * @throws InvalidLeadException When the transition is not allowed.
     */
    public function markAsLost(): void
    {
        $this->transitionTo(LeadStatus::LOST);
    }
}
