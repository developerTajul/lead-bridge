<?php

declare(strict_types=1);

namespace Core\Pipeline\Domain\DataObjects;

use Core\Pipeline\Domain\Entities\PipelineEntity;

/**
 * Read model for the pipeline detail view. Pairs the pipeline with the stage
 * rows it was read with, already flattened to plain arrays so the repository
 * can hand back something the framework-free layers can read.
 *
 * The stage rows carry the keys of the columns they came from; the
 * PipelineResponseMapper turns them into the nested response DTOs.
 */
final readonly class PipelineDetailData
{
    /**
     * @param PipelineEntity $pipeline
     * @param array<int, array<string, mixed>> $stages
     */
    public function __construct(
        public PipelineEntity $pipeline,
        public array $stages = [],
    ) {}
}
