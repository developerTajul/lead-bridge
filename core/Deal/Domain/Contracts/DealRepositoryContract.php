<?php

declare(strict_types=1);

namespace Core\Deal\Domain\Contracts;

use Core\Deal\Domain\Entities\DealEntity;

/**
 * PORT: persistence for deals.
 */
interface DealRepositoryContract
{
    /**
     * Persists the deal and returns the stored deal carrying the identifier
     * the write assigned.
     *
     * @param DealEntity $deal
     *
     * @return DealEntity
     */
    public function save(DealEntity $deal): DealEntity;

    /**
     * Reads a deal while holding a row lock, so a concurrent write cannot slip
     * between the read and the save.
     *
     * The lock is a storage concern: callers ask for a deal they mean to
     * change, and the adapter decides how that is made safe.
     *
     * @param int $id
     *
     * @return DealEntity|null Null when no deal carries the id.
     */
    public function findByIdWithLock(int $id): ?DealEntity;

    /**
     * Persists changes to an existing deal.
     *
     * @param DealEntity $deal
     *
     * @return DealEntity The deal as stored.
     */
    public function update(DealEntity $deal): DealEntity;

    /**
     * Deletes a deal.
     *
     * @param DealEntity $deal
     *
     * @return void
     */
    public function delete(DealEntity $deal): void;

    /**
     * The name of a stage, or null when no stage carries the id.
     *
     * A deal carries its stage as an ID, but the rule about which stages close
     * a deal is stated in names, so a caller needs the name to apply it.
     *
     * @param int $dealStageId
     *
     * @return string|null
     */
    public function findStageNameById(int $dealStageId): ?string;
}
