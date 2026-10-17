<?php

declare(strict_types=1);

namespace Core\Lead\Application\Contracts;

use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Domain\ValueObjects\LeadFilter;
use Core\Shared\Domain\DataObjects\PaginatedResult;

/**
 * Read side of the lead persistence port. Returns Application read models,
 * so it lives in the Application layer.
 */
interface LeadQueryContract
{
    /**
     * Returns the leads matching the given filter criteria.
     *
     * @param LeadFilter $filter
     *
     * @return PaginatedResult Items are LeadProjectionDTO instances.
     */
    public function getFilteredLeads(LeadFilter $filter): PaginatedResult;

    /**
     * Finds a lead by its ID.
     *
     * @param int $id
     *
     * @return LeadProjectionDTO|null
     */
    public function findById(int $id): ?LeadProjectionDTO;
}
