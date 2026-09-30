# Paginated Index Action Builder Skill (Data Retrieval Use-Case)

## Description

This skill safely implements the paginated data retrieval workflow (Index/GET) for an existing module. It updates the repository contracts, infrastructure implementation, service orchestration, and injects the `index()` method along with the corresponding GET route without destructively overwriting existing delivery layer code.

## Context & Rules

- **Non-Destructive Policy (Safe Append):** You MUST NOT overwrite, modify, or delete existing constructors, injected dependencies, or methods in the Controller. You will only APPEND the new `index()` method.
- **Separation of Concerns (Hexagonal Architecture):** The HTTP Controller must remain thin. It should extract the `per_page` query parameter, delegate execution to the Service layer, unpack the Result, and return a view.
- **Pagination Boundary (STRICT):** Eloquent's `LengthAwarePaginator` MUST NEVER leak into the Domain or Application layers. The Infrastructure layer MUST convert it into a pure `Core\Shared\Domain\DataObjects\PaginatedResult`.
- **Entity to DTO Mapping:** The Service layer MUST map the Entities inside the `PaginatedResult` to Response DTOs before returning them inside the `Result::success()` envelope.
- **PENDING ROUTES GUARD:** When scaffolding the index.blade.php view, you MUST use '#' for the href attributes of all action links (Edit, Show, Delete) by default. Never use the route() helper for these actions unless you have explicitly verified that the corresponding routes already exist in routes/web.php.

## Execution Steps

1. **Domain Contract Update (`Core\[Module]\Domain\Contracts\`):**
    - Append the `paginate(int $perPage = 10): PaginatedResult;` method signature.

2. **Infrastructure Repository Update (`Core\[Module]\Infrastructure\Repositories\`):**
    - Implement the `paginate()` method.
    - Fetch the Laravel paginator using `$this->paginateEloquent($perPage)`.
    - Map the items to pure Domain Entities using the injected EntityMapper.
    - Return a new `PaginatedResult` object containing the mapped entities and pagination meta-data (total, currentPage, perPage, lastPage).

3. **Application Service Update (`Core\[Module]\Application\Services\`):**
    - Append the `get[Module]s(int $perPage = 10): Result` method.
    - Call the repository's `paginate()` method.
    - Map the Entities inside the `PaginatedResult` to Response DTOs (e.g., using `$paginatedEntities->map(...)`).
    - Return `Result::success(data: $paginatedResponse)`. Catch exceptions and return `Result::failure()`.

4. **Safe Route Injection (`routes/web.php`):**
    - Provide the GET routing snippet to be appended for the `index` action.

5. **Safe Controller Injection (`App\Http\Controllers\Admin\`):**
    - Append the `index(Request $request): View` method.
    - Extract `$perPage = min((int) $request->input('per_page', 10), 100);`.
    - Call the service method, handle failures gracefully (passing an empty `PaginatedResult`), and return the view.

## Output

Deliver the generated code components cleanly. You MUST wrap each code component inside standard markdown code blocks (using backticks like `php` and `html`) to strictly preserve line breaks, indentation, and formatting.

## Canonical Controller Pattern

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Category\Application\Contracts\CategoryMapperContract;
use Core\Category\Application\Services\CategoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Core\Shared\Domain\DataObjects\PaginatedResult;

final class CategoryController extends Controller
{
    // Constructor and other existing methods remain untouched...

    public function index(Request $request): View
    {
        $perPage = min((int)$request->input('per_page', 10), 100);

        $result = $this->categoryService->getCategories($perPage);

        if ($result->isFailure()) {
            return view('backend.pages.category.index', [
                'categories' => [],
                'pagination' => new PaginatedResult(
                    items: [],
                    total: 0,
                    currentPage: 1,
                    perPage: $perPage,
                    lastPage: 1
                ),
                'error' => $result->message,
            ]);
        }

        /** @var PaginatedResult $paginatedCategories */
        $paginatedCategories =$result->data;

        return view('backend.pages.category.index', [
            'categories' => $paginatedCategories->items,
            'pagination' => $paginatedCategories
        ]);
    }
}