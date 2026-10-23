<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Deal\IndexDealRequest;
use App\Http\Requests\Web\Deal\StoreDealRequest;
use App\Http\Requests\Web\Deal\UpdateDealRequest;
use Core\Deal\Application\Contracts\DealMapperContract;
use Core\Deal\Application\DTOs\Requests\DealFilterDTO;
use Core\Deal\Application\Services\DealQueryService;
use Core\Deal\Application\Services\DealService;
use Core\Deal\Domain\Contracts\DealRepositoryContract;
use Core\Deal\Domain\DealRules;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Shows the deal pages and saves deal changes.
 */
final class DealController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const INVALID_FILTER_WARNING = 'Invalid filter applied. Showing unfiltered results.';

    /**
     * @param DealService            $deals
     * @param DealQueryService       $dealQuery
     * @param DealMapperContract     $mapper
     * @param DealRepositoryContract $dealRepository
     */
    public function __construct(
        private readonly DealService $deals,
        private readonly DealQueryService $dealQuery,
        private readonly DealMapperContract $mapper,
        private readonly DealRepositoryContract $dealRepository,
    ) {}

    /**
     * Shows the paginated deal list.
     *
     * @param IndexDealRequest $request
     *
     * @return View
     */
    public function index(IndexDealRequest $request): View
    {
        // A stale bookmark or hand-edited URL must not break the list, so an
        // unusable filter value is dropped and reported instead of rejected.
        $queryParams = $request->query();
        $queryParams['per_page'] = min(
            (int) $request->input('per_page', self::DEFAULT_PER_PAGE),
            DealRules::MAX_PER_PAGE,
        );

        [$dealFilterDTO, $wasFilterDropped] = $this->buildFilterDTO($queryParams);

        if ($wasFilterDropped) {
            session()->flash('warning', self::INVALID_FILTER_WARNING);
        }

        $result = $this->dealQuery->getPaginatedDeals($dealFilterDTO);

        if ($result->isFailure()) {
            return view('admin.deals.index', array_merge($this->formOptions(), [
                'deals' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $dealFilterDTO->perPage,
                    lastPage: 1,
                ),
                'filters' => $queryParams,
                'error' => $result->getMessage(),
            ]));
        }

        /** @var PaginatedResult $paginatedDeals */
        $paginatedDeals = $result->getData();

        return view('admin.deals.index', array_merge($this->formOptions(), [
            'deals' => $paginatedDeals->items,
            'pagination' => $paginatedDeals,
            'filters' => $queryParams,
        ]));
    }

    /**
     * Builds the list criteria, falling back to an unfiltered list when a
     * supplied value is unusable. The mapper is strict, so the rejection is
     * caught here rather than failing the page.
     *
     * @param array $queryParams
     *
     * @return array{0: DealFilterDTO, 1: bool} The criteria, and whether a value
     *                                          had to be dropped.
     */
    private function buildFilterDTO(array $queryParams): array
    {
        try {
            return [$this->mapper->mapToFilterDTO($queryParams), false];
        } catch (InvalidArgumentException) {
            // The value just rejected cannot be reused for the page size, so
            // the fallback is an unfiltered list at the default size.
            return [new DealFilterDTO(perPage: self::DEFAULT_PER_PAGE), true];
        }
    }

    /**
     * Shows the create deal form.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.deals.create', $this->formOptions());
    }

    /**
     * Saves a new deal.
     *
     * @param StoreDealRequest $request
     *
     * @return RedirectResponse
     */
    public function store(StoreDealRequest $request): RedirectResponse
    {
        try {
            $storeDealDTO = $this->mapper->mapToStoreDTO($request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        $result = $this->deals->createDeal($storeDealDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.deals.index')->with('success', $result->getMessage());
    }

    /**
     * Shows one deal.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function show(string $id): View|RedirectResponse
    {
        $result = $this->dealQuery->getDealDetails($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.deals.index')->with('error', $result->getMessage());
        }

        return view('admin.deals.show', ['deal' => $result->getData()]);
    }

    /**
     * Shows the edit deal form.
     *
     * The detail read already carries every editable field, so the form is
     * prefilled from it and the activities are left unused here.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function edit(string $id): View|RedirectResponse
    {
        $result = $this->dealQuery->getDealDetails($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.deals.index')->with('error', $result->getMessage());
        }

        return view('admin.deals.edit', array_merge(
            ['deal' => $result->getData()],
            $this->formOptions(),
        ));
    }

    /**
     * Saves changes to a deal.
     *
     * The stage's name is read before the mapper runs, because whether the new
     * stage closes the deal is stated in names and only storage knows them.
     *
     * @param UpdateDealRequest $request
     * @param string            $id
     *
     * @return RedirectResponse
     */
    public function update(UpdateDealRequest $request, string $id): RedirectResponse
    {
        try {
            $updateDealDTO = $this->mapper->mapToUpdateDTO(
                id: (int) $id,
                validatedData: $request->validated(),
                stageName: $this->stageNameFor((int) $request->validated()['deal_stage_id']),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        $result = $this->deals->updateDeal($updateDealDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.deals.index')->with('success', $result->getMessage());
    }

    /**
     * Removes a deal.
     *
     * @param string $id
     *
     * @return RedirectResponse
     */
    public function destroy(string $id): RedirectResponse
    {
        $result = $this->deals->deleteDeal((int) $id);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage());
        }

        return redirect()->route('admin.deals.index')->with('success', $result->getMessage());
    }

    /**
     * The rows the deal pages offer for each of their pickers. The list page
     * uses the pipelines for its filter and the form uses all four. A failure
     * here is not worth failing the page over: the page renders with empty
     * pickers and the save reports what is missing.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $result = $this->dealQuery->getFormOptions();

        if ($result->isFailure()) {
            return [];
        }

        return ['options' => $result->getData()];
    }

    /**
     * The name of the stage a form submission moves the deal to.
     *
     * @param int $dealStageId
     *
     * @return string|null
     */
    private function stageNameFor(int $dealStageId): ?string
    {
        return $this->dealRepository->findStageNameById($dealStageId);
    }
}
