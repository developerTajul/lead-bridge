<?php

declare(strict_types=1);

namespace Core\Pipeline\Domain\DataObjects;

use Core\Pipeline\Domain\Entities\PipelineEntity;

/**
 * Read model for the Kanban board: the pipeline and its stages, each carrying
 * the deals that sit in it.
 */
final readonly class KanbanBoardData
{
    /**
     * @param PipelineEntity                 $pipeline
     * @param array<int, KanbanStageData>    $stages
     */
    public function __construct(
        public PipelineEntity $pipeline,
        public array $stages = [],
    ) {}
}
