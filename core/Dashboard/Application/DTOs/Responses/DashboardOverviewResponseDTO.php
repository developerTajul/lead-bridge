<?php

declare(strict_types=1);

namespace Core\Dashboard\Application\DTOs\Responses;

/**
 * Shape the whole dashboard is handed to the delivery layer in: the headline
 * figures, the pipeline overview and the activity feed. One payload serves the
 * Blade page and the JSON endpoint.
 */
final readonly class DashboardOverviewResponseDTO
{
    /**
     * @param DashboardMetricsResponseDTO        $metrics
     * @param array<int, DashboardStageSummaryResponseDTO> $stages
     * @param array<int, DashboardActivityResponseDTO>     $upcomingActivities
     */
    public function __construct(
        public DashboardMetricsResponseDTO $metrics,
        public array $stages,
        public array $upcomingActivities,
    ) {}
}
