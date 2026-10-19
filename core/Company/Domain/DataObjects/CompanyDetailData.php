<?php

declare(strict_types=1);

namespace Core\Company\Domain\DataObjects;

use Core\Company\Domain\Entities\CompanyEntity;

/**
 * Read model for the company detail view. Pairs a company with the rows of its
 * related records, already flattened to plain arrays so the repository can
 * hand back something the framework-free layers can read.
 *
 * The related rows carry the keys of the columns they came from; the
 * CompanyResponseMapper turns them into the nested response DTOs.
 */
final readonly class CompanyDetailData
{
    /**
     * @param CompanyEntity        $company
     * @param array<int, array<string, mixed>> $contacts
     * @param array<int, array<string, mixed>> $deals
     * @param array<int, array<string, mixed>> $activities
     */
    public function __construct(
        public CompanyEntity $company,
        public array $contacts = [],
        public array $deals = [],
        public array $activities = [],
    ) {}
}
