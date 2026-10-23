<?php

declare(strict_types=1);

namespace Core\Dashboard\Infrastructure\Repositories;

use App\Models\DealStage;
use Core\Dashboard\Domain\Contracts\DashboardBoardContract;
use Core\Dashboard\Domain\DataObjects\PipelineBoardSummary;

/**
 * Eloquent-backed aggregation for the dashboard's pipeline overview.
 *
 * Reads the stages table and totals the deals sitting in each. Reports across
 * the pipeline and deal modules, so it is kept out of both of their
 * repositories.
 *
 * @implements DashboardBoardContract
 */
final class DashboardBoardRepository implements DashboardBoardContract
{
    /**
     * Returns one summary row per stage, ordered by pipeline and then by the
     * order the stages are worked through.
     *
     * The totals are aggregated in the database rather than by loading every
     * deal: a board summary counts, it does not list.
     *
     * @return array<int, PipelineBoardSummary> Empty when no stage exists.
     */
    public function getStageSummaries(): array
    {
        $stages = DealStage::query()
            ->with('pipeline:id,name')
            ->withCount('deals')
            // The amount column sums to a decimal, so the figure is rounded to
            // the two decimals it carries rather than reported as a float with
            // whatever noise the driver returns.
            ->withSum('deals as total_value', 'amount')
            ->orderBy('pipeline_id')
            ->orderBy('order_position')
            ->get(['id', 'pipeline_id', 'name', 'order_position', 'probability']);

        return $stages
            ->map(fn(DealStage $stage) => new PipelineBoardSummary(
                id: (int) $stage->id,
                pipelineId: (int) $stage->pipeline_id,
                pipelineName: $stage->pipeline?->name ?? '',
                name: (string) $stage->name,
                orderPosition: (int) $stage->order_position,
                probability: (int) $stage->probability,
                dealsCount: (int) $stage->deals_count,
                totalValue: round((float) $stage->total_value, 2),
            ))
            ->all();
    }
}
