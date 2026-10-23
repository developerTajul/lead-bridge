<?php

declare(strict_types=1);

namespace Core\Pipeline\Domain\Contracts;

use Core\Pipeline\Domain\Entities\PipelineEntity;

/**
 * PORT: persistence for pipelines and their stages.
 */
interface PipelineRepositoryContract
{
    /**
     * Persists the pipeline together with its stages and returns the stored
     * pipeline carrying the identifiers the write assigned.
     *
     * The pipeline row and its stages are one graph, so the adapter writes them
     * inside a transaction and a failure leaves neither behind.
     *
     * @param PipelineEntity $pipeline
     *
     * @return PipelineEntity The stored pipeline with its stages.
     */
    public function save(PipelineEntity $pipeline): PipelineEntity;

    /**
     * Reads a pipeline together with its stages while holding a row lock, so a
     * concurrent write cannot slip between the read and the save.
     *
     * The lock is a storage concern: callers ask for a pipeline they mean to
     * change, and the adapter decides how that is made safe.
     *
     * @param int $id
     *
     * @return PipelineEntity|null Null when no pipeline carries the id.
     */
    public function findByIdWithLock(int $id): ?PipelineEntity;

    /**
     * Persists changes to an existing pipeline and its stages, then returns the
     * stored pipeline.
     *
     * An edit replaces the stage set, so stages the update does not carry are
     * dropped. The pipeline row and its stages are one graph, so the adapter
     * writes them inside a transaction and a failure leaves neither behind.
     *
     * @param PipelineEntity $pipeline
     *
     * @return PipelineEntity The stored pipeline with its stages.
     */
    public function update(PipelineEntity $pipeline): PipelineEntity;
}
