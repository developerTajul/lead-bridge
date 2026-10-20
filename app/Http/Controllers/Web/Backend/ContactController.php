<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Contact\IndexContactRequest;
use App\Http\Requests\Web\Contact\StoreContactRequest;
use App\Http\Requests\Web\Contact\UpdateContactRequest;
use Core\Contact\Application\Contracts\ContactMapperContract;
use Core\Contact\Application\DTOs\Requests\ContactFilterDTO;
use Core\Contact\Application\Services\ContactQueryService;
use Core\Contact\Application\Services\ContactService;
use Core\Contact\Domain\ContactRules;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Shows the contact pages and saves contact changes.
 */
final class ContactController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const INVALID_FILTER_WARNING = 'Invalid filter applied. Showing unfiltered results.';

    /**
     * @param ContactService        $contacts
     * @param ContactQueryService   $contactQuery
     * @param ContactMapperContract $mapper
     */
    public function __construct(
        private readonly ContactService $contacts,
        private readonly ContactQueryService $contactQuery,
        private readonly ContactMapperContract $mapper,
    ) {}

    /**
     * Shows the paginated contact list.
     *
     * @param IndexContactRequest $request
     *
     * @return View
     */
    public function index(IndexContactRequest $request): View
    {
        // A stale bookmark or hand-edited URL must not break the list, so an
        // unusable filter value is dropped and reported instead of rejected.
        $queryParams = $request->query();
        $queryParams['per_page'] = min(
            (int) $request->input('per_page', self::DEFAULT_PER_PAGE),
            ContactRules::MAX_PER_PAGE,
        );

        [$contactFilterDTO, $wasFilterDropped] = $this->buildFilterDTO($queryParams);

        if ($wasFilterDropped) {
            session()->flash('warning', self::INVALID_FILTER_WARNING);
        }

        $result = $this->contactQuery->getPaginatedContacts($contactFilterDTO);

        if ($result->isFailure()) {
            return view('admin.contacts.index', [
                'contacts' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $contactFilterDTO->perPage,
                    lastPage: 1,
                ),
                'filters' => $queryParams,
                'error' => $result->getMessage(),
            ]);
        }

        /** @var PaginatedResult $paginatedContacts */
        $paginatedContacts = $result->getData();

        return view('admin.contacts.index', [
            'contacts' => $paginatedContacts->items,
            'pagination' => $paginatedContacts,
            'filters' => $queryParams,
        ]);
    }

    /**
     * Builds the list criteria, falling back to an unfiltered list when a
     * supplied value is unusable. The mapper is strict, so the rejection is
     * caught here rather than failing the page.
     *
     * @param array $queryParams
     *
     * @return array{0: ContactFilterDTO, 1: bool} The criteria, and whether a
     *                                             value had to be dropped.
     */
    private function buildFilterDTO(array $queryParams): array
    {
        try {
            return [$this->mapper->mapToFilterDTO($queryParams), false];
        } catch (InvalidArgumentException) {
            // The value just rejected cannot be reused for the page size, so
            // the fallback is an unfiltered list at the default size.
            return [new ContactFilterDTO(perPage: self::DEFAULT_PER_PAGE), true];
        }
    }

    /**
     * Shows the create contact form.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.contacts.create');
    }

    /**
     * Saves a new contact.
     *
     * @param StoreContactRequest $request
     *
     * @return RedirectResponse
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $storeContactDTO = $this->mapper->mapToStoreDTO($request->validated());
        $result = $this->contacts->createContact($storeContactDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.contacts.index')->with('success', $result->getMessage());
    }

    /**
     * Shows one contact.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function show(string $id): View|RedirectResponse
    {
        $result = $this->contactQuery->getContactDetails($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.contacts.index')->with('error', $result->getMessage());
        }

        return view('admin.contacts.show', ['contact' => $result->getData()]);
    }

    /**
     * Shows the edit contact form.
     *
     * The detail read already carries every editable field, so the form is
     * prefilled from it and the related companies and activities are left
     * unused here.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function edit(string $id): View|RedirectResponse
    {
        $result = $this->contactQuery->getContactDetails($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.contacts.index')->with('error', $result->getMessage());
        }

        return view('admin.contacts.edit', ['contact' => $result->getData()]);
    }

    /**
     * Saves changes to a contact.
     *
     * @param UpdateContactRequest $request
     * @param string               $id
     *
     * @return RedirectResponse
     */
    public function update(UpdateContactRequest $request, string $id): RedirectResponse
    {
        $updateContactDTO = $this->mapper->mapToUpdateDTO((int) $id, $request->validated());
        $result = $this->contacts->updateContact($updateContactDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.contacts.index')->with('success', $result->getMessage());
    }

    /**
     * Removes a contact.
     *
     * @param string $id
     *
     * @return RedirectResponse
     */
    public function destroy(string $id): RedirectResponse
    {
        $result = $this->contacts->deleteContact((int) $id);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage());
        }

        return redirect()->route('admin.contacts.index')->with('success', $result->getMessage());
    }
}
