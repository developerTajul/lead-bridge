<?php

declare(strict_types=1);

namespace Core\Lead\Infrastructure\Mappers;

use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Maps model arrays to LeadEntity and to the lead read model.
 */
final class LeadEntityMapper
{
    /**
     * Rebuilds a datetime from a stored value. Datetimes are persisted as UTC
     * wall-clock strings, so they are read back as UTC too: parsing them in the
     * application timezone would shift the instant on every round trip.
     *
     * @param mixed $value
     *
     * @return DateTimeImmutable|null
     */
    private function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if (empty($value)) {
            return null;
        }

        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }

    /**
     * Builds a pure domain entity from a model array.
     *
     * @param array $data
     *
     * @return LeadEntity
     */
    public function toEntity(array $data): LeadEntity
    {
        return new LeadEntity(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            companyName: $data['company_name'] ?? null,
            status: LeadStatus::tryFrom((string) $data['status']) ?? LeadStatus::NEW,
            source: (string) ($data['source'] ?? 'website'),
            createdAt: (string) $data['created_at'],
            updatedAt: (string) $data['updated_at'],
        );
    }

    /**
     * Builds the read model, pairing the entity with its stored timestamps.
     *
     * @param array $data
     *
     * @return LeadProjectionDTO
     */
    public function toProjection(array $data): LeadProjectionDTO
    {
        return new LeadProjectionDTO(
            entity: $this->toEntity($data),
            createdAt: $this->toDateTime($data['created_at'] ?? null),
            updatedAt: $this->toDateTime($data['updated_at'] ?? null),
        );
    }
}
