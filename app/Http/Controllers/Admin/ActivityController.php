<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityStoreRequest;
use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\Services\ActivityService;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows the activity form and saves new activities.
 */
final class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityService $activities,
        private readonly ActivityMapperContract $mapper,
    ) {}

    /**
     * Shows the paginated activity list.
     *
     * @param Request $request
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $perPage = min((int) $request->input('per_page', 10), 100);

        $result = $this->activities->getActivities($perPage);

        if ($result->isFailure()) {
            return view('admin.activities.index', [
                'activities' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $perPage,
                    lastPage: 1,
                ),
                'error' => $result->getMessage(),
            ]);
        }

        /** @var PaginatedResult $paginatedActivities */
        $paginatedActivities = $result->getData();

        return view('admin.activities.index', [
            'activities' => $paginatedActivities->items,
            'pagination' => $paginatedActivities,
        ]);
    }

    /**
     * Shows the create activity form.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.activities.create');
    }

    /**
     * Saves a new activity.
     *
     * @param ActivityStoreRequest $request
     *
     * @return RedirectResponse
     */
    public function store(ActivityStoreRequest $request): RedirectResponse
    {
        $activityCreateDTO = $this->mapper->mapToCreateDTO($request->validated());
        $result = $this->activities->createActivity($activityCreateDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.activities.index')->with('success', $result->getMessage());
    }
}