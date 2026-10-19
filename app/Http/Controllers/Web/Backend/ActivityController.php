<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityStoreRequest;
use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\ActivityRules;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows the activity form and saves new activities.
 */
final class ActivityController extends Controller
{
    private const DEFAULT_PER_PAGE = 10;

    private const INVALID_FILTER_WARNING = 'Invalid filter applied. Showing unfiltered results.';

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
        // A stale bookmark or hand-edited URL must not break the list, so an
        // unusable filter value is dropped and reported instead of rejected.
        $queryParams = $request->query();
        $queryParams['per_page'] = min(
            (int) $request->input('per_page', self::DEFAULT_PER_PAGE),
            ActivityRules::MAX_PER_PAGE,
        );

        [$filterDTO, $ignoredFilters] = $this->mapper->mapToFilterDTOLenient($queryParams);

        if ($ignoredFilters !== []) {
            session()->flash('warning', self::INVALID_FILTER_WARNING);
        }

        $result = $this->activities->getActivities($filterDTO);

        if ($result->isFailure()) {
            return view('admin.activities.index', [
                'activities' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $filterDTO->perPage,
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
        return view('admin.activities.create', [
            'trackableTypes' => $this->mapper->toTrackableTypeOptions(),
        ]);
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
        $result = $this->activities->create($activityCreateDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.activities.index')->with('success', $result->getMessage());
    }
}