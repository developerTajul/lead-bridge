<?php

declare(strict_types=1);

namespace Core\Lead\Domain\Contracts;

use Core\Lead\Domain\DataObjects\LeadData;
use Core\Lead\Domain\Entities\LeadEntity;

/**
 * Persistence interface for the Lead module.
 */
interface LeadRepositoryContract
{
    /**
     * Creates a new lead and returns it as a Domain Entity.
     *
     * @param LeadData $leadData
     *
     * @return LeadEntity
     */
    public function create(LeadData $leadData): LeadEntity;

    /**
     * Finds a lead by its ID as a Domain Entity.
     *
     * Named findEntityById rather than findById because LeadRepository also
     * implements LeadQueryContract, whose findById returns a read model. A
     * class cannot declare both return types under one method name.
     *
     * @param int $id
     *
     * @return LeadEntity|null
     */
    public function findEntityById(int $id): ?LeadEntity;

    /**
     * Persists the lead's current status.
     *
     * @param LeadEntity $lead
     *
     * @return void
     */
    public function saveStatus(LeadEntity $lead): void;

    public function findByEmail(string $email): ?LeadEntity;
    public function findByPhone(string $phone): ?LeadEntity;
}
