---
name: store-action-builder
description: Implements the store (create form submit / POST) action for an existing module - FormRequest, Request DTO, Request Mapper and its contract, POST route, controller store(), and form action binding. Use when asked to add or implement the store/save action for a module.
---

# Store Action Builder Skill (Data Persistence Use-Case)

## Description

This skill safely implements the data-saving workflow (Form Submit/POST) for an existing module. It generates request validation, the Request DTO, the Request Mapper with its contract, and appends the `store()` method with its POST route, without destructively overwriting existing delivery-layer code. It also builds the use-case side that the action depends on (Entity `create()`, Repository `create()`, Command Service `create{ModuleName}`) when it does not exist yet, so the action works end to end, like the delete, edit, and update skills.

> **This work follows the Rich Domain Model.** Business rules (valid state, invariants, derived values) live in the Entity, never in the FormRequest, Controller, or Service. The store action is a **write** use case, so it goes through the Command Service. Read `AGENTS.md`, and load `.claude/rules/02-framework-adapter-laravel.txt` (route and Eloquent mapping rules), `04-crud-pipeline.txt`, `05-rich-domain-and-services.txt`, `06-ports-and-portability.txt`, and `08-events-and-queues.txt` before writing code.

**Placeholders used in this skill:**

| Placeholder    | Meaning                               | Example                        |
| -------------- | ------------------------------------- | ------------------------------ |
| `{ModuleName}` | PascalCase module name                | `Category`, `Product`, `Brand` |
| `{module}`     | lowercase/plural route & view segment | `categories`, `products`       |
| `{entity}`     | camelCase singular variable name      | `category`, `product`, `brand` |
| `{MODULE}`     | UPPER_SNAKE_CASE error-key prefix     | `CATEGORY`, `PRODUCT`, `BRAND` |

## Context & Rules

- **Source of Truth:** Analyze the provided SQL database schema to formulate the exact fields and validation rules. Skip primary keys, auto-managed timestamps, soft deletes, and domain-generated fields (`slug`, `uuid`, `token`). Never invent fields.
- **Non-Destructive Policy (Safe Append):** You MUST NOT overwrite, modify, reorder, or delete existing methods, routes, bindings, or injected dependencies. You only APPEND. The one sanctioned exception: widening an Entity's `int $id` to `?int` (Step 4), and only when the Entity still has the old non-nullable type. If the Controller does not yet receive the Command Service or the MapperContract, append them as new promoted parameters at the END of the existing constructor. Never reorder or remove existing parameters, and list each addition in your report.
- **Separation of Concerns (Hexagonal Architecture):** The HTTP Controller stays thin: validate (FormRequest) -> Mapper builds the DTO -> ONE Command Service method -> translate the `Result` into a redirect. It never calls the Repository and never contains a business rule.
- **Vertical slice (build what is missing):** The Controller calls `{ModuleName}CommandService::create{ModuleName}(DTO)`. Read what exists first. If that method already exists, reuse it and read its real signature. If it does not (the usual case right after the module scaffold, where the Service and Repository are empty skeletons), build the use-case side in Step 4, following 04 (STEP 2-3) and 05. Append only; never overwrite an existing method.
- **Domain error keys:** The Service's `Result` `errorCode` holds domain keys (e.g. `{MODULE}_SLUG_CONFLICT`), never raw HTTP statuses.
- **Core stays framework-free:** No `Illuminate\*` import in `core/` Domain/Application. The FormRequest and Controller are delivery code under `app/`. The Request DTO and the MapperContract live in Core.
- **Route Binding:** Once the `store` route is defined, update the `create.blade.php` form action from `action="#"` to the named route.

## Execution Steps

1. **Request Validation (`App\Http\Requests\Admin\{ModuleName}StoreRequest`):**
    - Generate a standard Laravel FormRequest.
    - Define rules strictly matching the schema: presence and type (`required`, `string`, `max:255`, `numeric`, `image`, `nullable`). For enum columns use `Rule::in({ModuleName}Status::values())` (or the module's real enum).
    - Authorize the request by returning `true`.
    - FormRequests carry validation rules only. They must not persist or transform data, and they must not duplicate a business rule that belongs in the Entity.
    - Uniqueness is a business rule enforced by the Command Service (a domain conflict exception becomes `Result::failure`). Do NOT add `unique:` rules unless the existing modules in this codebase already do. Verify and follow them.

2. **Request DTO Generation (`Core\{ModuleName}\Application\DTOs\Requests\{ModuleName}CreateDTO`):**
    - Create it as an immutable `readonly class` with a constructor of promoted `readonly` properties ONLY.
    - NO static factory methods (`fromArray()`, `fromRequest()`, `fromValidated()`...) and nothing that depends on a framework request structure. All translation happens in the Request Mapper.
    - Properties for uploaded files MUST be typed `mixed`, never `Illuminate\Http\UploadedFile` or any framework type.
    - Add one immutable `with*()` clone for each derived/deferred field that exists in the schema (`withSlug(string $slug): self` for a `slug` column, `withStoreImage(string $path): self` or `withLogo(...)` for an image/file column). Each returns a NEW instance with every promoted property re-passed by name. Never mutate in place.
    - Consistency guard: a `with*()` clone may only reference properties the constructor really declares. Keep the property list, the Mapper, and every clone in step.

3. **Request Mapper and Contract:**
    - Contract: `Core\{ModuleName}\Application\Contracts\{ModuleName}MapperContract` with `mapToCreateDTO(array $validatedData): {ModuleName}CreateDTO`. The parameter is a plain `array` named `$validatedData`. NEVER type-hint a FormRequest or any framework class.
    - Adapter: `Core\{ModuleName}\Application\Mappers\{ModuleName}Mapper`, a `final class` implementing the contract. It builds the DTO with named arguments and is the ONLY place that translates request data to the DTO. An uploaded file lands on a `mixed` property (`$validatedData['image'] ?? null`).
    - If the contract and mapper already exist, append the `mapToCreateDTO` method and leave the rest untouched.
    - Bind the contract to the adapter once in `app/Providers/AppServiceProvider.php` (append only). Do not rebind anything that already exists.

4. **Use-Case Side (Entity, Repository, Command Service) - build only what is missing:**
    - **Entity:** append the named constructor `{ModuleName}Entity::create(...)` to the `readonly` Entity. It enforces the creation invariants and returns a NEW Entity (no setters). Do not invent rules the business does not have. If something else reacts to creation, the Entity carries a plain-PHP `{ModuleName}CreatedEvent` (in `Domain/Events/`) in its events list. A new Entity has no id yet, so `id` is `?int` (03 and 05 R1: the scaffold already generates it that way). If an older Entity still declares `int $id`, widen it to `?int` as the ONE sanctioned edit to an existing property, and list it in your report.
    - **Repository contract and adapter:** declare `create({ModuleName}Entity ${entity}): {ModuleName}Entity` on `{ModuleName}RepositoryContract`. Implement it in `{ModuleName}Repository` with `createRecord()` fed by `{ModuleName}EntityMapper::toPersistenceArray()`, and return `toEntity($model->getAttributes())`. Catch framework failures in the adapter and rethrow a standard `RuntimeException`.
    - **Command Service:** append `create{ModuleName}({ModuleName}CreateDTO ${entity}CreateDTO): Result` following 04 STEP 3 and the reference below. If a Shared port it needs (`SlugGeneratorContract`, `FileUploaderContract`, `EventDispatcherContract`) is not in the constructor yet, append it as a new promoted parameter at the END. Upload directory names are `private const` values at the top of the Service (AGENTS.md - INFRASTRUCTURE STRING ENCAPSULATION MANDATE).
    - **Domain exception:** if the schema has a unique slug and no `{ModuleName}SlugConflictException` exists, add it to `Domain/Exceptions/`.

```php
/**
 * Creates a {entity}.
 */
public function create{ModuleName}({ModuleName}CreateDTO ${entity}CreateDTO): Result
{
    $uploadedPath = null;

    $this->transactionManager->beginTransaction();

    try {
        // Derived fields go through the DTO's with*() clones. Omit what the schema does not have:
        //   slug: $this->slugGenerator->generateUniqueSlug(sourceValue: ..., table: '{module}')
        //   file: $this->fileUploader->upload(file: ..., directory: self::IMAGE_DIRECTORY)
        //         and keep the stored path in $uploadedPath for cleanup.

        $new{ModuleName}Entity = {ModuleName}Entity::create(/* from the DTO */); // rules run INSIDE the Entity
        $events = $new{ModuleName}Entity->releaseEvents(); // BEFORE persisting

        $saved{ModuleName}Entity = $this->{entity}Repository->create($new{ModuleName}Entity);

        $this->transactionManager->commit();
    } catch ({ModuleName}SlugConflictException $exception) {
        return $this->rollbackAndFail(
            message: $exception->getMessage(),
            errorCode: '{MODULE}_SLUG_CONFLICT',
            uploadedPath: $uploadedPath,
        );
    } catch (\Throwable $exception) {
        $this->logger->error(
            "Failed to create {module}: " . $exception->getMessage(),
            ['exception' => $exception],
        );

        return $this->rollbackAndFail(
            message: "We couldn't create the {ModuleName} right now. Please try again.",
            errorCode: '{MODULE}_CREATE_ERROR',
            uploadedPath: $uploadedPath,
        );
    }

    // Committed. A failure below is logged and never turns the create into a failure.
    $this->dispatchEventsQuietly($events); // omit if the module has no events

    return Result::success(
        data: $this->responseMapper->toResponseDTO($saved{ModuleName}Entity),
        message: "{ModuleName} created successfully.",
    );
}
```

    Reuse the `rollbackAndFail(...)` and `dispatchEventsQuietly(array $events)` helpers if the Service already has them. Otherwise add them as in the update skill: `rollbackAndFail` calls `rollback()`, deletes a newly uploaded file quietly, and returns `Result::failure`. Verify the real `Result`, mapper, and port signatures before writing.

5. **Safe Route Injection (`routes/web.php`):**
    - Routes use `Route::resource()` inside the existing middleware/prefix/name group. Do NOT hand-write a `Route::post(...)` line.
    - If the module's resource is registered with `->only([...])`, add `'store'` to it. If it uses `->except([...])`, remove `'store'` from it. If it has no restriction, change nothing.

```php
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('{module}', {ModuleName}Controller::class)->only(['create', 'store']);
});
```

    This yields `POST /admin/{module}` named `admin.{module}.store`.

6. **Safe Controller Injection (`App\Http\Controllers\Admin\{ModuleName}Controller`):**

    **CONSTRUCTOR INJECTION MANDATE (STRICT):**
    - The Controller's `__construct()` MUST receive `{ModuleName}CommandService` AND `{ModuleName}MapperContract`. Add whichever is missing as appended promoted parameters.
    - `store()` receives ONLY the FormRequest. ALL other dependencies are constructor-injected, never method-injected.
    - **MAPPER VARIABLE NAMING RULE (STRICT):** the injected mapper is always named `$mapper` (e.g. `private readonly {ModuleName}MapperContract $mapper`).
    - Use semantic names for the Service (`${entity}CommandService`) and the DTO (`${entity}CreateDTO`), never `$dto` or `$coupons`.

    **SEMANTIC NAMING (STRICT):**
    - The mapper method is `mapToCreateDTO`, accepting a plain array named `$validatedData`.
    - The Controller calls `$this->mapper->mapToCreateDTO($request->validated())`.

    **Implementation:**
    - Append only the `store()` method. Build the DTO through the mapper, call ONE Command Service method, and use a guard clause (no ternary) on `$result->isFailure()`.
    - On failure: `back()->with('error', $result->message)->withInput()`.
    - On success: redirect to `admin.{module}.index` with the success message. Verify first that this route exists in `routes/web.php`. If it does not, redirect to `admin.{module}.create` instead and flag it in your report. Never redirect to a missing route.
    - Never catch exceptions in the Controller.

7. **View Update (`resources/views/backend/pages/{module}/create.blade.php`):**
    - Use the project's real views directory (the one that holds `index.blade.php`). Verify it, do not guess.
    - Change the form `action` from `#` to `{{ route('admin.{module}.store') }}`, keep `method="POST"` and `@csrf`, and remove the "store route not defined yet" reminder block left by the form scaffold.
    - Keep `enctype="multipart/form-data"` if and only if the schema has file/image columns. Change nothing else in the view.

## Unit Tests (moved to rule 07 - paused)

Testing is paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim tests. Test requirements live in `.claude/rules/07-testing.txt`, which is loaded only when the developer asks for tests.

## Verification Checklist (fill in only from what the generated code actually contains)

Claim only what you can point to in the code. A false claim is a defect.

- [ ] `create{ModuleName}` was either already present (reused, with its real signature read) or built in Step 4 without overwriting any existing method.
- [ ] Entity `create()` is a named constructor that enforces its invariants and returns a new `readonly` instance, with no setters.
- [ ] Repository `create()` takes and returns an Entity, uses `toPersistenceArray()` / `toEntity($model->getAttributes())`, and rethrows framework failures as `RuntimeException`.
- [ ] Command Service: transaction opened, every failure branch rolls back, a newly uploaded file is removed on failure, events are released BEFORE persisting and dispatched only after commit, and a failing cleanup or dispatch is logged without failing the create.
- [ ] No existing method, route, binding, or constructor parameter was overwritten, reordered, or removed. Each appended constructor parameter is listed in the report.
- [ ] FormRequest rules match the schema, skip PK/timestamps/domain-generated fields, and contain no business rule.
- [ ] Request DTO is in `Application/DTOs/Requests/`, is a `readonly class` with promoted properties only, has NO static factory, types uploaded files `mixed`, and has a `with*()` clone for each derived field that exists in the schema.
- [ ] The MapperContract accepts a plain `array $validatedData`, and the Mapper is the only place that builds the DTO from request data.
- [ ] Route uses `Route::resource()` inside the existing group, with no hand-written `Route::post` line.
- [ ] Controller receives the Command Service and `$mapper` through the constructor, `store()` takes only the FormRequest, and a guard clause is used instead of a ternary.
- [ ] The success redirect target route exists (verified), or the fallback was used and flagged.
- [ ] No framework import in Core; the contract is named `...Contract`; the binding was appended once.
- [ ] The form action uses `route('admin.{module}.store')`, with `@csrf`, and `enctype` present only if the schema has file/image columns.
- [ ] Namespaces, imports, and signatures (`Result`, mapper, Service) were verified against the codebase, not guessed.
- Unit tests are paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim them.
- [ ] After adding files under a new namespace path, run `composer dump-autoload`.

## Output

Deliver the generated code components cleanly. You MUST wrap each code component inside standard markdown code blocks (using backticks with the language, such as `php` and `blade`) to strictly preserve line breaks, indentation, and formatting. Keep conversational text to a minimum, and end with the filled-in verification checklist.

## Canonical Controller Pattern (Constructor Injection)

The example uses the Category module. Replace names with the module's real ones, and verify every import against the codebase.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryStoreRequest;
use Core\Category\Application\Contracts\CategoryMapperContract;
use Core\Category\Application\Services\CategoryCommandService;
use Illuminate\Http\RedirectResponse;

final class CategoryController extends Controller
{
    // Service and MapperContract are injected via the CONSTRUCTOR.
    public function __construct(
        private readonly CategoryCommandService $categoryCommandService,
        private readonly CategoryMapperContract $mapper, // NOTE: always $mapper
    ) {}

    /**
     * Saves the new category.
     */
    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        // Pass the VALIDATED ARRAY (not the request object) to keep Core framework-agnostic.
        $categoryCreateDTO = $this->mapper->mapToCreateDTO($request->validated());
        $result = $this->categoryCommandService->createCategory($categoryCreateDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->message)->withInput();
        }

        return redirect()->route('admin.categories.index')->with('success', $result->message);
    }
}
```

**Do NOT inject the MapperContract directly into the `store()` method signature. The only exception to constructor-only injection is the HTTP FormRequest itself.**

**Framework-Agnostic Rule:** The MapperContract in the Core Application Layer MUST accept a plain `array` (parameter named `$validatedData`). NEVER type-hint a Laravel FormRequest. The Controller passes `$request->validated()` to the mapper.
