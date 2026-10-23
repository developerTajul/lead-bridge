<?php

declare(strict_types=1);

namespace Core\Lead\Application\Mappers;

use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Application\DTOs\Responses\LeadResponseDTO;
use Core\Lead\Domain\Entities\LeadEntity;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

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
        return $this->build(
            entity: $projection->entity,
            createdAt: $projection->createdAt?->format(DateTimeInterface::ATOM),
            updatedAt: $projection->updatedAt?->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * Builds the response DTO from a lead the write path already holds, so a
     * command result leaves in the same shape a query result does.
     *
     * @param LeadEntity $entity
     *
     * @return LeadResponseDTO
     */
    public function toResponseDTOFromEntity(LeadEntity $entity): LeadResponseDTO
    {
        return $this->build(
            entity: $entity,
            createdAt: $this->toAtom($entity->createdAt),
            updatedAt: $this->toAtom($entity->updatedAt),
        );
    }

    /**
     * Assembles the response from an entity and its already-formatted dates.
     *
     * @param LeadEntity  $entity
     * @param string|null $createdAt
     * @param string|null $updatedAt
     *
     * @return LeadResponseDTO
     */
    private function build(LeadEntity $entity, ?string $createdAt, ?string $updatedAt): LeadResponseDTO
    {
        $status = $entity->getStatus();

        return new LeadResponseDTO(
            id: $entity->id,
            name: $entity->name,
            email: $entity->email,
            phone: $entity->phone,
            company_name: $entity->companyName,
            status: $status->value,
            status_label: $status->label(),
            source: $entity->source,
            // Every datetime leaves on the same ISO-8601 wire format. The list
            // view reformats it for display; a client parses it as-is.
            created_at: $createdAt,
            updated_at: $updatedAt,
        );
    }

    /**
     * Formats a stored datetime the way the read path does. Stored datetimes
     * are UTC wall-clock strings, so they are read back as UTC too.
     *
     * @param string $stored
     *
     * @return string|null
     */
    private function toAtom(string $stored): ?string
    {
        if ($stored === '') {
            return null;
        }

        return (new DateTimeImmutable($stored, new DateTimeZone('UTC')))
            ->format(DateTimeInterface::ATOM);
    }
}
