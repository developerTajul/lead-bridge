<?php

declare(strict_types=1);

namespace Core\Lead\Application\DTOs\Responses;

/**
 * Shape the lead list is handed to the delivery layer in.
 */
final readonly class LeadResponseDTO
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $company_name,
        public string $status,
        public string $status_label,
        public string $source,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {}
}
