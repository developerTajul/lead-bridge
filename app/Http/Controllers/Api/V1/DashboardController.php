<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\Dashboard\Application\Contracts\DashboardMapperContract;
use Core\Dashboard\Application\Services\DashboardQueryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController extends Controller
{
    /**
     * @param DashboardQueryService   $dashboardQuery
     * @param DashboardMapperContract $mapper
     */
    public function __construct(
        private readonly DashboardQueryService $dashboardQuery,
        private readonly DashboardMapperContract $mapper,
    ) {}

    /**
     * Get the whole dashboard: the headline figures, the pipeline overview and
     * the activity feed.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $result = $this->dashboardQuery->getOverview($this->mapper->mapToFilterDTO());

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $overview = $result->getData();

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => [
                'metrics' => $this->formatMetrics($overview->metrics),
                'pipeline' => array_map($this->formatStage(...), $overview->stages),
                'upcoming_activities' => $overview->upcomingActivities,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Get the headline figures on their own.
     *
     * @return JsonResponse
     */
    public function metrics(): JsonResponse
    {
        $result = $this->dashboardQuery->getMetrics();

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $this->formatMetrics($result->getData()),
        ], Response::HTTP_OK);
    }

    /**
     * Groups the flat metrics payload into the panels the response reports.
     * The DTO carries them flat because the Blade page reads them as scalars;
     * a JSON client expects them grouped by the module they came from.
     *
     * @param \Core\Dashboard\Application\DTOs\Responses\DashboardMetricsResponseDTO $metrics
     *
     * @return array<string, mixed>
     */
    private function formatMetrics(\Core\Dashboard\Application\DTOs\Responses\DashboardMetricsResponseDTO $metrics): array
    {
        return [
            'revenue' => [
                'won_revenue' => $metrics->wonRevenue,
                'open_pipeline_value' => $metrics->openPipelineValue,
                'currency' => $metrics->currency,
            ],
            'deals' => [
                'total_deals' => $metrics->totalDeals,
                'won_deals' => $metrics->wonDeals,
                'lost_deals' => $metrics->lostDeals,
                'open_deals' => $metrics->openDeals,
                'win_rate' => $metrics->winRate,
            ],
            'leads' => [
                'total_leads' => $metrics->totalLeads,
                'converted_leads' => $metrics->convertedLeads,
                'conversion_rate' => $metrics->conversionRate,
            ],
            'activities' => [
                'pending' => $metrics->pendingActivities,
                'completed' => $metrics->completedActivities,
            ],
        ];
    }

    /**
     * @param \Core\Dashboard\Application\DTOs\Responses\DashboardStageSummaryResponseDTO $stage
     *
     * @return array<string, mixed>
     */
    private function formatStage(\Core\Dashboard\Application\DTOs\Responses\DashboardStageSummaryResponseDTO $stage): array
    {
        return [
            'id' => $stage->id,
            'pipeline_id' => $stage->pipelineId,
            'pipeline_name' => $stage->pipelineName,
            'name' => $stage->name,
            'order_position' => $stage->orderPosition,
            'probability' => $stage->probability,
            'deals_count' => $stage->dealsCount,
            'total_value' => $stage->totalValue,
        ];
    }
}
