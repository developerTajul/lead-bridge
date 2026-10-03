<?php

declare(strict_types=1);

namespace Core\Lead\Infrastructure\Mappers;

use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;

/**
 * Maps model arrays to LeadEntity.
 */
final class LeadEntityMapper
{
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
}
