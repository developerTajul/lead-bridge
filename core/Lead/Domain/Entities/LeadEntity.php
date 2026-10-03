<?php

declare(strict_types=1);

namespace Core\Lead\Domain\Entities;

use Core\Lead\Domain\Enums\LeadStatus;

/**
 * Pure domain entity representing a lead.
 */
readonly class LeadEntity
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $companyName,
        public LeadStatus $status,
        public string $source,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
