<?php

declare(strict_types=1);

namespace Core\Lead\Application\DTOs\Responses;

use Core\Lead\Domain\Entities\LeadEntity;
use DateTimeImmutable;

/**
 * Read model for the lead list. Pairs the lead entity with its stored
 * timestamps, so the entity itself stays free of projection state.
 */
final readonly class LeadProjectionDTO
{
    public function __construct(
        public LeadEntity $entity,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}
