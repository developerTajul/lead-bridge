<?php

declare(strict_types=1);

namespace Core\Company\Infrastructure\Mappers;

use Core\Company\Domain\Entities\CompanyEntity;

/**
 * Maps model arrays to CompanyEntity and to the company table's columns.
 */
final class CompanyEntityMapper
{
    /**
     * Builds a pure domain entity from a model array.
     *
     * @param array $data
     *
     * @return CompanyEntity
     */
    public function toEntity(array $data): CompanyEntity
    {
        return CompanyEntity::reconstitute(
            id: (int) $data['id'],
            name: (string) $data['name'],
            website: $data['website'] ?? null,
            industry: $data['industry'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    /**
     * Flattens the entity's own state onto the company table's columns.
     *
     * @param CompanyEntity $company
     *
     * @return array<string, mixed>
     */
    public function toPersistenceArray(CompanyEntity $company): array
    {
        return [
            'name' => $company->name,
            'website' => $company->website,
            'industry' => $company->industry,
            'phone' => $company->phone,
            'address' => $company->address,
        ];
    }
}
