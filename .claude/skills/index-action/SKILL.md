---
name: index-action
description: Implements the paginated index (list) action for an existing module - repository paginate(), Query Service method, GET index route, controller index(), and index view. Use when asked to add or implement the paginated index/list page for a module.
---

# Paginated Index Action Builder Skill (Data Retrieval Use-Case)

## Description

This skill safely implements the paginated data retrieval workflow (Index/GET) for an existing module. It updates the repository contract, infrastructure implementation, Query Service orchestration, and adds the `index()` method with its GET route, without destructively overwriting existing delivery-layer code.

> **This work follows the Rich Domain Model.** Business rules live in the Entity; Services are orchestrators only. The index is a **read** use case, so it goes through a Query Service. Read `AGENTS.md`, and load `.claude/rules/05-rich-domain-and-services.txt` and `.claude/rules/06-ports-and-portability.txt` before writing code.

**Placeholders used in this skill:**

| Placeholder      | Meaning                               | Example                            |
| ---------------- | ------------------------------------- | ---------------------------------- |
| `{ModuleName}`   | PascalCase module name                | `Category`, `Product`, `Brand`     |
| `{ModulePlural}` | PascalCase plural for method names    | `Categories`, `Products`, `Brands` |
| `{module}`       | lowercase/plural route & view segment | `categories`, `products`, `brands` |
| `{entity}`       | camelCase singular variable name      | `category`, `product`, `brand`     |
| `{MODULE}`       | UPPER_SNAKE_CASE error-key prefix     | `CATEGORY`, `PRODUCT`, `BRAND`     |

## Context & Rules

- **Query Service, read-only:** The list logic goes in `{ModuleName}QueryService`. No transaction manager, event dispatcher, queue, or repository write call. The constructor needs only the repository contract, the response mapper, and the logger (constructor property promotion).
- **Legacy modules:** If the module is an older one (single `{ModuleName}Service`, no `{ModuleName}QueryService`), append the method to the existing Service. Do not split or migrate it unless the prompt asks. All other rules still apply.
- **Non-Destructive Policy (Safe Append):** You MUST NOT overwrite, modify, or delete existing methods, routes, views, or injected dependencies. You only APPEND. If the Controller does not yet receive the Query Service, append exactly ONE new promoted parameter to the end of its existing constructor. Never reorder or remove existing parameters, and list that addition in your report.
- **Separation of Concerns (Hexagonal Architecture):** The HTTP Controller stays thin. It reads the `per_page` query parameter, delegates to the Query Service, unpacks the `Result`, and returns a view. It never calls the Repository.
- **Pagination Boundary (STRICT):** Eloquent's `LengthAwarePaginator` MUST NEVER leak into the Domain or Application layers. The Infrastructure layer converts it into a pure `Core\Shared\Domain\DataObjects\PaginatedResult`. Verify the real constructor and property names of `PaginatedResult` in the codebase before using them.
- **Entity to ResponseDTO Mapping:** The Query Service MUST map the Entities inside the `PaginatedResult` to `{ModuleName}ResponseDTO` objects (through `{ModuleName}ResponseMapper`) before returning them inside `Result::success()`. The Controller and view never receive an Entity or an Eloquent model.
- **Domain error keys:** `Result` `errorCode` holds domain keys (e.g. `{MODULE}_LIST_ERROR`), never raw HTTP statuses.
- **Core stays framework-free:** No `Illuminate\*` import, facade, or helper in Core Domain/Application. Ports use the `...Contract` suffix.
- **PENDING ROUTES GUARD:** When scaffolding `index.blade.php`, use `#` for the `href` of every action link (Edit, Show, Delete) by default. Never use `route()` for those actions unless you have verified in `routes/web.php` that the route exists. Put a visible TODO next to each placeholder.

## Execution Steps

1. **Domain Contract Update (`Core\{ModuleName}\Domain\Contracts\{ModuleName}RepositoryContract`):**
    - Append `public function paginate(int $perPage = 10): PaginatedResult;` (import `Core\Shared\Domain\DataObjects\PaginatedResult`).
    - The returned items are Domain Entities. Do not declare Eloquent types.

2. **Infrastructure Repository Update (`Core\{ModuleName}\Infrastructure\Repositories\{ModuleName}Repository`):**
    - Implement `paginate()`.
    - Fetch the Laravel paginator with `$this->paginateEloquent($perPage)`.
    - Map models to Domain Entities with the injected EntityMapper, using the Collection `map()` chained with `all()`. Do NOT use native `array_map()` here (AGENTS.md - COLLECTION MAPPING PREFERENCE).
    - Return a new `PaginatedResult` with the mapped entities and the pagination metadata (total, currentPage, perPage, lastPage).
    - Catch framework-specific failures inside the adapter and rethrow them as a standard `RuntimeException`. Never swallow them silently.

```php
/**
 * Gets one page of {module} as Domain Entities.
 */
public function paginate(int $perPage = 10): PaginatedResult
{
    $paginator = $this->paginateEloquent($perPage);

    $entities = $paginator
        ->getCollection()
        ->map(fn ($model) => $this->mapper->toEntity($model->toArray()))
        ->all();

    return new PaginatedResult(
        items: $entities,
        total: $paginator->total(),
        currentPage: $paginator->currentPage(),
        perPage: $paginator->perPage(),
        lastPage: $paginator->lastPage(),
    );
}
```

3. **Application Query Service Update (`Core\{ModuleName}\Application\Services\{ModuleName}QueryService`):**
    - Append `get{ModulePlural}(int $perPage = 10): Result`.
    - Call the repository's `paginate()`.
    - Map the Entities inside the `PaginatedResult` to `{ModuleName}ResponseDTO` objects. Use the `PaginatedResult`'s own mapping method if it has one; otherwise build a new `PaginatedResult` with the mapped items and the same metadata. Never pass Entities onward.
    - Return `Result::success(...)` carrying the paginated ResponseDTOs.
    - Wrap the flow in `try`/`catch (\Throwable)`. Log with `$this->logger->error()` (include the exception object), then return a generic `Result::failure()` with `{MODULE}_LIST_ERROR`. Never expose the raw exception message.
    - Stay read-only: no transaction, event, or write call.

```php
/**
 * Gets one page of {module} for the index page.
 */
public function get{ModulePlural}(int $perPage = 10): Result
{
    try {
        $paginatedEntities = $this->{entity}Repository->paginate($perPage);

        $paginatedResponse = new PaginatedResult(
            items: array_map(
                fn (${ModuleName}Entity ${entity}Entity) => $this->responseMapper->toResponseDTO(${entity}Entity),
                $paginatedEntities->items,
            ),
            total: $paginatedEntities->total,
            currentPage: $paginatedEntities->currentPage,
            perPage: $paginatedEntities->perPage,
            lastPage: $paginatedEntities->lastPage,
        );

        return Result::success(
            data: $paginatedResponse,
            message: "{ModulePlural} retrieved successfully.",
        );
    } catch (\Throwable $exception) {
        $this->logger->error(
            "Failed to list {module}: " . $exception->getMessage(),
            ['exception' => $exception],
        );

        return Result::failure(
            message: "We couldn't load the {module} right now. Please try again.",
            errorCode: '{MODULE}_LIST_ERROR',
        );
    }
}
```

    Verify the real `Result` signatures, the mapper method name, and the `PaginatedResult` constructor against the codebase. Replace `${ModuleName}Entity` in the closure with the real Entity class name and import it.

4. **Safe Route Injection (`routes/web.php`):**
    - Routes use `Route::resource()` inside the existing middleware/prefix/name group. Do NOT hand-write a `Route::get(...)` line.
    - If the module's resource is already registered with `->only([...])`, add `'index'` to it. If it uses `->except([...])`, remove `'index'` from it. If it is registered with no restriction, change nothing.
    - If the module has no resource yet, add one limited to `->only(['index'])`.

```php
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('{module}', {ModuleName}Controller::class)->only(['index']);
});
```

    This yields `GET /admin/{module}` named `admin.{module}.index`.

5. **Safe Controller Injection (`App\Http\Controllers\Admin\{ModuleName}Controller`):**
    - Append the `index(Request $request): View` method. Do not touch existing methods.
    - Clamp the page size: `$perPage = max(1, min((int) $request->input('per_page', 10), 100));`. The `max(1, ...)` stops `per_page=0`, a negative number, or non-numeric input from reaching the paginator.
    - Call the Query Service, use a guard clause on `$result->isFailure()` (passing an empty `PaginatedResult`), and return the view.
    - Never catch exceptions here and never call the Repository.

6. **Index View (`resources/views/backend/pages/{module}/index.blade.php`):**
    - Create it only if it does not exist. If it exists, do not overwrite it. Report what would need adding instead.
    - Render a table from `${module}` (a list of `{ModuleName}ResponseDTO`). Show only ResponseDTO properties that exist (verify against the DTO). Never call Entity methods.
    - Action links (Edit, Show, Delete) follow the PENDING ROUTES GUARD: `href="#"` with a visible TODO.
    - Show `$error` when it is set.
    - `PaginatedResult` is framework-free, so Laravel's `->links()` is not available. Build the pagination controls from its metadata, keeping the `per_page` query value:

```blade
@if ($pagination->lastPage > 1)
    <nav aria-label="Pagination">
        @if ($pagination->currentPage > 1)
            <a href="{{ request()->fullUrlWithQuery(['page' => $pagination->currentPage - 1]) }}">Previous</a>
        @endif

        <span>Page {{ $pagination->currentPage }} of {{ $pagination->lastPage }}</span>

        @if ($pagination->currentPage < $pagination->lastPage)
            <a href="{{ request()->fullUrlWithQuery(['page' => $pagination->currentPage + 1]) }}">Next</a>
        @endif
    </nav>
@endif
```

## Unit Tests (moved to rule 07 - paused)

Testing is paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim tests. Test requirements live in `.claude/rules/07-testing.txt`, which is loaded only when the developer asks for tests.

## Verification Checklist (fill in only from what the generated code actually contains)

Claim only what you can point to in the code. A false claim is a defect.

- [ ] No existing method, route, view, or dependency was overwritten, reordered, or removed. Any constructor addition is a single appended parameter and is listed in the report.
- [ ] The list logic is in the Query Service (or the existing single Service for legacy modules), with no transaction, event, queue, or write call.
- [ ] Eloquent's paginator does not appear in Domain or Application code; the repository returns a `PaginatedResult` of Entities.
- [ ] The repository maps with Collection `map()->all()`, not `array_map()`.
- [ ] The Query Service returns `{ModuleName}ResponseDTO` items inside `Result`, never Entities or models.
- [ ] `errorCode` values are domain keys (`{MODULE}_LIST_ERROR`), not raw HTTP statuses.
- [ ] Route uses `Route::resource()` inside the existing group, with no hand-written `Route::get` line.
- [ ] Controller uses a guard clause on `isFailure()`, clamps `per_page` with `max(1, min(...))`, and never touches the Repository.
- [ ] No framework import or facade in Core Domain/Application; ports use the `...Contract` suffix.
- [ ] Index view uses `#` with a visible TODO for unverified action links, and pagination controls are built from `PaginatedResult`.
- [ ] Namespaces, imports, and signatures (`Result`, `PaginatedResult`, mapper) were verified against the codebase, not guessed.
- Unit tests are paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim them.
- [ ] After adding files under a new namespace path, run `composer dump-autoload`.

## Output

Deliver the generated code components cleanly. You MUST wrap each code component inside standard markdown code blocks (using backticks with the language, such as `php` and `blade`) to strictly preserve line breaks, indentation, and formatting. Keep conversational text to a minimum, and end with the filled-in verification checklist.

## Canonical Controller Pattern

The example uses the Category module. Replace names with the module's real ones, and verify imports against the codebase.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Category\Application\Contracts\CategoryMapperContract;
use Core\Category\Application\Services\CategoryQueryService;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CategoryController extends Controller
{
    // Existing constructor and methods remain untouched. If CategoryQueryService
    // is not injected yet, append ONE promoted parameter at the end of the constructor:
    // private readonly CategoryQueryService $categoryQueryService

    /**
     * Shows the paginated category list.
     */
    public function index(Request $request): View
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));

        $result = $this->categoryQueryService->getCategories($perPage);

        if ($result->isFailure()) {
            return view('backend.pages.categories.index', [
                'categories' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $perPage,
                    lastPage: 1,
                ),
                'error' => $result->message,
            ]);
        }

        /** @var PaginatedResult $paginatedCategories */
        $paginatedCategories = $result->data;

        return view('backend.pages.categories.index', [
            'categories' => $paginatedCategories->items,
            'pagination' => $paginatedCategories,
        ]);
    }
}
```
