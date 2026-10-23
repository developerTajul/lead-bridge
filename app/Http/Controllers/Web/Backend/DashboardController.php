<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use Core\Dashboard\Application\Contracts\DashboardMapperContract;
use Core\Dashboard\Application\Services\DashboardQueryService;
use Illuminate\View\View;

/**
 * Shows the dashboard page.
 */
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
     * Shows the dashboard.
     *
     * A failing read does not fail the page: it renders with an error message
     * and empty panels, so a user still reaches the navigation and the rest of
     * the app rather than a stack trace.
     *
     * @return View
     */
    public function index(): View
    {
        $result = $this->dashboardQuery->getOverview($this->mapper->mapToFilterDTO());

        if ($result->isFailure()) {
            return view('admin.dashboard', [
                'metrics' => null,
                'stages' => [],
                'upcomingActivities' => [],
                'error' => $result->getMessage(),
            ]);
        }

        $overview = $result->getData();

        return view('admin.dashboard', [
            'metrics' => $overview->metrics,
            'stages' => $overview->stages,
            'upcomingActivities' => $overview->upcomingActivities,
        ]);
    }
}
