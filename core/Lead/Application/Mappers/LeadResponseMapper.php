<?php

declare(strict_types=1);

namespace Core\Lead\Application\Mappers;

use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Application\DTOs\Responses\LeadResponseDTO;
use DateTimeInterface;

/**
 * Maps a lead and its timestamps to LeadResponseDTO.
 */
final class LeadResponseMapper
{
    /**
     * Builds the response DTO from the lead projection.
     *
     * @param LeadProjectionDTO $projection
     *
     * @return LeadResponseDTO
     */
    public function toResponseDTO(LeadProjectionDTO $projection): LeadResponseDTO
    {
        $entity = $projection->entity;

        return new LeadResponseDTO(
            id: $entity->id,
            name: $entity->name,
            email: $entity->email,
            phone: $entity->phone,
            company_name: $entity->companyName,
            status: $entity->status->value,
            status_label: $entity->status->label(),
            source: $entity->source,
            // Every datetime leaves on the same ISO-8601 wire format. The list
            // view reformats it for display; a client parses it as-is.
            created_at: $projection->createdAt?->format(DateTimeInterface::ATOM),
            updated_at: $projection->updatedAt?->format(DateTimeInterface::ATOM),
        );
    }
}
