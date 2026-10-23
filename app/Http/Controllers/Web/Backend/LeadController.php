<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backend;

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

    public function __construct(
        private readonly LeadServiceInterface $leadService,
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
        $queryParams = $request->query();

        $queryParams['per_page'] = max(
            LeadRules::MIN_PER_PAGE, 
            min(
                (int) $request->input('per_page', LeadRules::DEFAULT_PER_PAGE), 
                LeadRules::MAX_PER_PAGE
            )
        );

        [$leadFilterDTO, $ignoredFilters] = $this->mapper->mapToFilterDTOLenient($queryParams);

        if ($ignoredFilters !== []) {
            session()->flash('warning', __('leads.invalid_filter'));
        }

        $result = $this->leadService->getLeads($leadFilterDTO);


        if ($result->isFailure()) {
            return view('admin.leads.index', [
                'leads' => [],
                'pagination' => PaginatedResult::empty($leadFilterDTO->perPage), 
                'statusOptions' => $this->mapper->toStatusOptions(),
                'filters' => $queryParams,
                'error' => $result->getMessage(),
            ]);
        }

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
        $result = $this->leadService->createLead($leadCreateDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.leads.index')->with('success', $result->getMessage());
    }
}
