<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LeadStoreRequest;
use Core\Lead\Application\Contracts\LeadMapperContract;
use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Lead\Domain\LeadRules;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows the lead list and saves new leads.
 */
final class LeadController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const INVALID_FILTER_WARNING = 'Invalid filter applied. Showing unfiltered results.';

    public function __construct(
        private readonly LeadServiceInterface $leads,
        private readonly LeadMapperContract $mapper,
    ) {}

    /**
     * Shows the paginated lead list.
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
            LeadRules::MAX_PER_PAGE,
        );

        [$leadFilterDTO, $ignoredFilters] = $this->mapper->mapToFilterDTOLenient($queryParams);

        if ($ignoredFilters !== []) {
            session()->flash('warning', self::INVALID_FILTER_WARNING);
        }

        $result = $this->leads->getLeads($leadFilterDTO);

        if ($result->isFailure()) {
            return view('admin.leads.index', [
                'leads' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $leadFilterDTO->perPage,
                    lastPage: 1,
                ),
                'statusOptions' => $this->mapper->toStatusOptions(),
                'filters' => $queryParams,
                'error' => $result->getMessage(),
            ]);
        }

        /** @var PaginatedResult $paginatedLeads */
        $paginatedLeads = $result->getData();

        return view('admin.leads.index', [
            'leads' => $paginatedLeads->items,
            'pagination' => $paginatedLeads,
            'statusOptions' => $this->mapper->toStatusOptions(),
            'filters' => $queryParams,
        ]);
    }

    /**
     * Saves a new lead.
     *
     * @param LeadStoreRequest $request
     *
     * @return RedirectResponse
     */
    public function store(LeadStoreRequest $request): RedirectResponse
    {
        $leadCreateDTO = $this->mapper->mapToCreateDTO($request->validated());
        $result = $this->leads->createLead($leadCreateDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.leads.index')->with('success', $result->getMessage());
    }
}
