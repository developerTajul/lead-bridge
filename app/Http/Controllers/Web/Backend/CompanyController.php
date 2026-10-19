<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use Core\Company\Application\Contracts\CompanyMapperContract;
use Core\Company\Application\DTOs\Requests\CompanyFilterDTO;
use Core\Company\Application\Services\CompanyQueryService;
use Core\Company\Application\Services\CompanyService;
use Core\Company\Domain\CompanyRules;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Shows the company pages and saves company changes.
 */
final class CompanyController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const ERROR_NOT_FOUND = 'COMPANY_NOT_FOUND';

    private const INVALID_FILTER_WARNING = 'Invalid filter applied. Showing unfiltered results.';

    /**
     * @param CompanyService      $companies
     * @param CompanyQueryService $companyQuery
     * @param CompanyMapperContract $mapper
     */
    public function __construct(
        private readonly CompanyService $companies,
        private readonly CompanyQueryService $companyQuery,
        private readonly CompanyMapperContract $mapper,
    ) {}

    /**
     * Shows the paginated company list.
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
            CompanyRules::MAX_PER_PAGE,
        );

        [$companyFilterDTO, $wasFilterDropped] = $this->buildFilterDTO($queryParams);

        if ($wasFilterDropped) {
            session()->flash('warning', self::INVALID_FILTER_WARNING);
        }

        $result = $this->companyQuery->getPaginatedCompanies($companyFilterDTO);

        if ($result->isFailure()) {
            return view('admin.companies.index', [
                'companies' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $companyFilterDTO->perPage,
                    lastPage: 1,
                ),
                'filters' => $queryParams,
                'error' => $result->getMessage(),
            ]);
        }

        /** @var PaginatedResult $paginatedCompanies */
        $paginatedCompanies = $result->getData();

        return view('admin.companies.index', [
            'companies' => $paginatedCompanies->items,
            'pagination' => $paginatedCompanies,
            'filters' => $queryParams,
        ]);
    }

    /**
     * Builds the list criteria, falling back to an unfiltered list when a
     * supplied value is unusable. The module has no lenient mapper, so the
     * rejection is caught here rather than failing the page.
     *
     * @param array $queryParams
     *
     * @return array{0: CompanyFilterDTO, 1: bool} The criteria, and whether a
     *                                             value had to be dropped.
     */
    private function buildFilterDTO(array $queryParams): array
    {
        try {
            return [$this->mapper->mapToFilterDTO($queryParams), false];
        } catch (InvalidArgumentException) {
            // The value just rejected cannot be reused for the page size, so
            // the fallback is an unfiltered list at the default size.
            return [new CompanyFilterDTO(perPage: self::DEFAULT_PER_PAGE), true];
        }
    }

    /**
     * Shows the create company form.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.companies.create');
    }

    /**
     * Saves a new company.
     *
     * @param StoreCompanyRequest $request
     *
     * @return RedirectResponse
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $storeCompanyDTO = $this->mapper->mapToStoreDTO($request->validated());
        $result = $this->companies->createCompany($storeCompanyDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.companies.index')->with('success', $result->getMessage());
    }

    /**
     * Shows one company.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function show(string $id): View|RedirectResponse
    {
        $result = $this->companyQuery->getCompanyDetails($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.companies.index')->with('error', $result->getMessage());
        }

        return view('admin.companies.show', ['company' => $result->getData()]);
    }

    /**
     * Shows the edit company form.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function edit(string $id): View|RedirectResponse
    {
        $result = $this->companyQuery->getCompanyForEdit($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.companies.index')->with('error', $result->getMessage());
        }

        return view('admin.companies.edit', ['company' => $result->getData()]);
    }

    /**
     * Saves changes to a company.
     *
     * @param UpdateCompanyRequest $request
     * @param string               $id
     *
     * @return RedirectResponse
     */
    public function update(UpdateCompanyRequest $request, string $id): RedirectResponse
    {
        $updateCompanyDTO = $this->mapper->mapToUpdateDTO((int) $id, $request->validated());
        $result = $this->companies->updateCompany($updateCompanyDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.companies.index')->with('success', $result->getMessage());
    }

    /**
     * Removes a company.
     *
     * @param string $id
     *
     * @return RedirectResponse
     */
    public function destroy(string $id): RedirectResponse
    {
        $result = $this->companies->deleteCompany($id);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage());
        }

        return redirect()->route('admin.companies.index')->with('success', $result->getMessage());
    }
}
