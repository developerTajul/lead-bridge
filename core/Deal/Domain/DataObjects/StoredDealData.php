<?php

declare(strict_types=1);

namespace Core\Deal\Domain\DataObjects;

use Core\Deal\Domain\Entities\DealEntity;

/**
 * A deal together with the rows the list and detail reads drag in. The related
 * rows are flattened, so nothing above Infrastructure has to know how the
 * relations are read. A list read fills the deal and its named links; a detail
 * read also fills the pipeline, the stage and the activities.
 */
final readonly class StoredDealData
{
    /**
     * @param DealEntity                       $deal
     * @param array<string, mixed>|null        $company
     * @param array<string, mixed>|null        $contact
     * @param array<string, mixed>|null        $pipeline
     * @param array<string, mixed>|null        $stage
     * @param array<int, array<string, mixed>> $activities
     */
    public function __construct(
        public DealEntity $deal,
        public ?array $company = null,
        public ?array $contact = null,
        public ?array $pipeline = null,
        public ?array $stage = null,
        public array $activities = [],
    ) {}
}
