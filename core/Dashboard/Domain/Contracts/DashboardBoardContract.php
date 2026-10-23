<?php

declare(strict_types=1);

namespace Core\Dashboard\Domain\Contracts;

use Core\Dashboard\Domain\DataObjects\PipelineBoardSummary;

/**
 * Read side of the dashboard's pipeline overview port: one summary row per
 * stage, across every pipeline, which no single module repository owns.
 */
interface DashboardBoardContract
{
    /**
     * Returns one summary row per stage, ordered by pipeline and then by the
     * order the stages are worked through.
     *
     * @return array<int, PipelineBoardSummary> Empty when no stage exists.
     */
    public function getStageSummaries(): array;
}
