<?php

declare(strict_types=1);

namespace Core\Lead\Application\Mappers;

use Core\Lead\Application\Contracts\LeadMapperContract;
use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Lead\Domain\Enums\LeadStatus;

/**
 * Maps validated request input to Lead DTOs.
 *
 * @implements \Core\Lead\Application\Contracts\LeadMapperContract
 */
final class LeadMapper implements LeadMapperContract
{
    /**
     * Builds a LeadCreateDTO from validated input.
     *
     * @param array $validatedData
     *
     * @return LeadCreateDTO
     */
    public function mapToCreateDTO(array $validatedData): LeadCreateDTO
    {
        return new LeadCreateDTO(
            name: (string) $validatedData['name'],
            email: $validatedData['email'] ?? null,
            phone: $validatedData['phone'] ?? null,
            companyName: $validatedData['company_name'] ?? null,
            status: LeadStatus::tryFrom((string) ($validatedData['status'] ?? 'new')) ?? LeadStatus::NEW,
            source: (string) ($validatedData['source'] ?? 'website'),
        );
    }
}
