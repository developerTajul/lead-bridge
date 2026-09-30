# Store Action Builder Skill (Data Persistence Use-Case)

## Description

This skill safely implements the data-saving workflow (Form Submit/POST) for an existing module. It generates request validation, DTOs, and injects the `store()` method along with the corresponding POST route without destructively overwriting existing delivery layer code.

## Context & Rules

- **Source of Truth:** Analyze the provided SQL database schema to formulate exact validation rules.
- **Non-Destructive Policy (Safe Append):** You MUST NOT overwrite, modify, or delete existing constructors, injected dependencies, or methods in the Controller. You will only APPEND the new `store()` method.
- **Separation of Concerns (Hexagonal Architecture):** The HTTP Controller must remain thin. It should only validate the request, map it to a DTO, delegate the execution to the relevant Service layer, and return a redirect response.
- **Route Binding:** Once the `store` route is defined, you MUST update the corresponding `create.blade.php` form action from `action="#"` to `action="{{ route('admin.[module].store') }}"`.

## Execution Steps

1. **Request Validation (`App\Http\Requests\Admin\`):**
    - Generate a standard Laravel FormRequest class (e.g., `[Module]StoreRequest`).
    - Define validation rules strictly matching the database schema (e.g., `required`, `string`, `max:255`, `unique`, `image`).
    - Authorize the request by returning `true`.

2. **DTO Generation (`Core\[Module]\Application\DTOs\`):**
    - Create a `readonly class` Data Transfer Object (e.g., `[Module]CreateDTO`) to ferry the validated data from the Request to the Service layer.

3. **Safe Route Injection (`routes/web.php`):**
    - Provide the POST routing snippet to be appended directly below the existing GET route.
    - Map the POST request to the Controller's `store` method.

4. **Safe Controller Injection (`App\Http\Controllers\Admin\`):**

    **CONSTRUCTOR INJECTION MANDATE (STRICT):**
    - The Controller's `__construct()` MUST receive the Module Service (e.g., `CategoryService`) AND the ModuleMapperContract (e.g., `CategoryMapperContract`).
    - The `store()` method MUST receive ONLY the FormRequest (e.g., `CategoryStoreRequest $request`).
    - ALL other class dependencies (including MapperContract and Services) MUST be injected via the constructor, NOT via method parameters.
    - If the MapperContract is not already in the constructor, you MUST modify the existing constructor to inject it.
    - **MAPPER VARIABLE NAMING RULE (STRICT):** Controllers MUST use `$mapper` as the variable name for the injected ModuleMapperContract dependency (e.g., `private readonly CategoryMapperContract $mapper`). This convention keeps constructor signatures clean and controller code fluent.

    **SEMANTIC NAMING (STRICT):**
    - The Mapper method for create use cases MUST be named `mapToCreateDTO`.
    - It MUST accept a plain array parameter named `$validatedData`.
    - The Controller calls `$this->mapper->mapToCreateDTO($request->validated())`.

    **Implementation:**
    - Inside `store()`: the DTO is built using `$this->mapper->mapToCreateDTO($request->validated())`.
    - Maps to DTO via the already-injected Mapper, delegates to Service, and returns a redirect.

5. **View Update:**
    - Provide the snippet to update the form `action` attribute in `resources/views/admin/[module]/create.blade.php` to use the named route `admin.[module].store`.

## Output

Deliver the generated code components cleanly. You MUST wrap each code component inside standard markdown code blocks (using backticks like `php` and `html`) to strictly preserve line breaks, indentation, and formatting.

## Canonical Controller Pattern (Constructor Injection)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Coupon\Application\Contracts\CouponMapperContract;
use Core\Coupon\Application\Services\CouponService;
use Illuminate\Http\RedirectResponse;

final class CategoryController extends Controller
{
    // BOTH Service and MapperContract injected via CONSTRUCTOR
    public function __construct(
        private readonly CouponService $coupons,
        private readonly CouponMapperContract $mapper  // NOTE: always $mapper
    ) {}

    // ONLY the FormRequest is passed to the action method
    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        // Pass VALIDATED ARRAY to keep Core framework-agnostic
        $categoryCreateDTO = $this->mapper->mapToCreateDTO($request->validated());
        $result = $this->coupons->createCategory($categoryCreateDTO);

        return $result->isFailure()
            ? back()->with('error', $result->message)->withInput()
            : redirect()->route('admin.categories.index')->with('success', $result->message);
    }
}
```

**Do NOT inject the MapperContract directly into the `store()` method signature. The only exception to constructor-only injection is the HTTP FormRequest itself.**

**Framework-Agnostic Rule:** The MapperContract in the Core Application Layer MUST accept a plain `array` (parameter named `$validatedData`) - NEVER type-hint a Laravel FormRequest. The Controller passes `$request->validated()` to the mapper.
