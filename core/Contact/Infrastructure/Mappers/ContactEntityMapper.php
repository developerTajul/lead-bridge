<?php

declare(strict_types=1);

namespace Core\Contact\Infrastructure\Mappers;

use Core\Contact\Domain\Entities\ContactEntity;

/**
 * Maps model arrays to ContactEntity and to the contacts table's columns.
 */
final class ContactEntityMapper
{
    /**
     * Builds a pure domain entity from a model array.
     *
     * @param array $data
     *
     * @return ContactEntity
     */
    public function toEntity(array $data): ContactEntity
    {
        return ContactEntity::reconstitute(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            phone: $data['phone'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    /**
     * Flattens the entity's own state onto the contacts table's columns.
     *
     * @param ContactEntity $contact
     *
     * @return array<string, mixed>
     */
    public function toPersistenceArray(ContactEntity $contact): array
    {
        return [
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
        ];
    }
}
