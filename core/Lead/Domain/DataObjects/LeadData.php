<?php

declare(strict_types=1);

namespace Core\Lead\Domain\DataObjects;

use Core\Lead\Domain\Enums\LeadStatus;

/**
 * Immutable data object for creating or updating a lead.
 */
readonly class LeadData
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $companyName = null,
        public LeadStatus $status = LeadStatus::NEW,
        public string $source = '',
    ) {}

    /**
     * Returns the data as an associative array suitable for database insertion/update.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->companyName,
            'status' => $this->status->value,
            'source' => $this->source,
        ];

        return array_filter($data, fn($value) => $value !== null);
    }
}
