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

    public function findByEmail(string $email): ?LeadEntity;
    public function findByPhone(string $phone): ?LeadEntity;
}
