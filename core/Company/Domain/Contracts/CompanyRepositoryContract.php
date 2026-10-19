<?php

declare(strict_types=1);

namespace Core\Company\Domain\Contracts;

use Core\Company\Domain\Entities\CompanyEntity;

/**
 * PORT: persistence for companies.
 */
interface CompanyRepositoryContract
{
    /**
     * Persists the entity, inserting it when it has no ID yet.
     *
     * @param CompanyEntity $company
     *
     * @return CompanyEntity
     */
    public function save(CompanyEntity $company): CompanyEntity;

    /**
     * Reads a company while holding a row lock, so a concurrent write cannot
     * slip between the read and the save.
     *
     * @param int $id
     *
     * @return CompanyEntity|null Null when no company carries the id.
     */
    public function findByIdWithLock(int $id): ?CompanyEntity;

    /**
     * Deletes a company.
     *
     * @param CompanyEntity $company
     *
     * @return void
     */
    public function delete(CompanyEntity $company): void;
}
