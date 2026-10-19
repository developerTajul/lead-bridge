---
name: edit-form-builder
description: Builds the Edit Form feature for a module (route, index link, controller edit action, Query Service read, repository findById, response mapper, edit.blade.php). Use when asked to add or implement the edit form for a module.
---

# Task: Implement `{ModuleName}` Edit Form Feature

Implement the `{ModuleName}` Edit Form feature based on the following specifications. **Ensure strict adherence to our Hexagonal Architecture.**

> **This work follows the Rich Domain Model.** Business rules live in the Entity, and Services are orchestrators only. The edit form is a **read** use case, so it goes through a Query Service. Read `AGENTS.md`, and load `.claude/rules/05-rich-domain-and-services.txt` and `.claude/rules/06-ports-and-portability.txt` before writing code.

> **On the code snippets below:** each one is a **reference implementation** showing the expected logic, structure, and step order — it is not a literal drop-in. Before writing any method, verify the exact signatures (`Result`, mapper methods, logger calls, etc.) and style conventions against **CLAUDE.md / AGENTS.md** and the existing codebase. If the real signature or convention differs from the reference, follow the real one — never the placeholder.

> **Placeholders used in this template:**
> - `{ModuleName}` — PascalCase entity name (e.g. `Category`, `Product`, `Brand`)
> - `{module}` — lowercase/plural route & path segment (e.g. `categories`, `products`, `brands`)
> - `{entity}` — camelCase variable name (e.g. `category`, `product`, `brand`)
> - `{MODULE}` — UPPER_SNAKE_CASE error-key prefix (e.g. `CATEGORY`, `PRODUCT`, `BRAND`)
> - `{FieldList}` — the entity's editable fields (e.g. `name, slug, status`)

---

## Architecture Rules for This Task (read first)

1. **Query Service.** Loading the edit form is a read. It goes in `{ModuleName}QueryService`. No transaction, no events, no queue, no write call.
2. **Service is an orchestrator.** Load, map to a ResponseDTO, return. Do not put business rules in it.
3. **ResponseDTO only.** The Service returns `Result` carrying `{ModuleName}ResponseDTO`. The Controller and the Blade view never receive an Entity or an Eloquent model.
4. **Domain error keys.** `Result` `errorCode` holds domain keys (e.g. `{MODULE}_NOT_FOUND`), never raw HTTP statuses like `'404'`. The Controller translates keys to responses.
5. **Ports only.** Core code (Domain and Application) uses `...Contract` ports and imports nothing from Laravel or WordPress. Eloquent stays in Infrastructure.
6. **Constructor injection.** The Controller gets its Service(s) and Mapper contract through the constructor. `edit()` takes only the route id.
7. **Legacy modules.** If `{ModuleName}` is an older module (single `{ModuleName}Service`, no `{ModuleName}QueryService`), add `get{ModuleName}ById` to the existing Service. Do not split it or migrate it unless the prompt asks. All other rules in this file still apply.

---

## Task 1: Routing

Ensure a `GET` route exists for the edit action. Routes are declared with `Route::resource()` inside the existing middleware/prefix/name group — **do not** hand-write a per-verb line.

| Key | Value |
|---|---|
| Route | `/admin/{module}/{{{entity}}}/edit` |
| Name | `admin.{module}.edit` |
| Controller Action | `{ModuleName}Controller@edit` |

```php
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('{module}', {ModuleName}Controller::class);
});
```

If the module's resource is already registered with `->only([...])`, add `'edit'` to it (and `'update'` later with the update feature). If it uses `->except([...])`, remove `'edit'` from it.

---

## Task 2: Index View Update

**File:** `resources/views/backend/pages/{module}/index.blade.php`

Update the "Edit" button in the index view table. Change the `href` attribute from `"#"` to point to the `admin.{module}.edit` route, passing the `{entity}` ID.

```blade
<a href="{{ route('admin.{module}.edit', ${entity}->id) }}" class="btn btn-primary">Edit</a>
```

`${entity}` in the index is the `{ModuleName}ResponseDTO` the index already receives. Do not change what the index passes to the view.

---

## Task 3: Controller Implementation

**File:** `{ModuleName}Controller.php`

> **Namespace/Imports Note:** Ensure the controller imports `View`, `RedirectResponse`, and `Result` correctly — do not invent or assume a namespace; verify it against the existing codebase.

Implement the `edit` method following the logic below (the code block is a reference — verify exact `Result`/return-type signatures against CLAUDE.md and the codebase). The method must:

1. **Delegate to the Query Service** — call `${entity}QueryService->get{ModuleName}ById($id)`. The Controller must never talk to the Repository directly.
2. **Get dependencies through the constructor.** Add `{ModuleName}QueryService` to the Controller's constructor (constructor property promotion) if it is not already injected. Do not inject it through the method.
3. **Check for failure** using `$result->isFailure()` with a guard clause. On failure, redirect back to `admin.{module}.index` with a flashed `error` message taken from `$result->message` — do not construct a custom error message here.
4. **Extract the data** from `$result->data` only after confirming success.
5. **Return the edit view**, passing the `{entity}` ResponseDTO via `compact()`.
6. **Never catch exceptions here** — error handling belongs to the Service layer; the Controller only reacts to the `Result` object.

```php
public function edit(int $id): View|RedirectResponse
{
    $result = $this->{entity}QueryService->get{ModuleName}ById($id);

    if ($result->isFailure()) {
        return redirect()->route('admin.{module}.index')->with('error', $result->message);
    }

    ${entity} = $result->data;

    return view('backend.pages.{module}.edit', compact('{entity}'));
}
```

---

## Task 4: Query Service Implementation

**File:** `{ModuleName}QueryService.php`

Implement the `get{ModuleName}ById` method following the logic below (the code block is a reference — verify `Result::failure`/`Result::success` signatures, mapper method name, and logger call format against CLAUDE.md and the codebase). The method must:

1. **Fetch the entity** by calling `{entity}Repository->findById($id)`.
2. **Handle the not-found case:** if the repository returns `null`, return `Result::failure()` with the error key `{MODULE}_NOT_FOUND` and a message stating the `{ModuleName}` was not found for that ID. Do not throw an exception for this case — it is an expected outcome, not a system error.
3. **Map the entity to a response DTO** using `$this->responseMapper->toResponseDTO()`, so the Service never leaks Domain Entities to the Controller/View layer.
4. **Return success:** wrap the DTO in `Result::success()` with a clear success message.
5. **Catch unexpected errors:** wrap the whole flow in a try/catch. On `\Throwable`, log the error (with the exception object, not just the message string) via `$this->logger->error()`, then return a generic `Result::failure()` with its own error key (`{MODULE}_FETCH_ERROR`) — never expose raw exception details to the end user.
6. **Stay read-only.** No transaction manager, no event dispatcher, no queue, no repository write call.
7. **Never return the raw Entity or Model directly** — the only valid return type from this method is `Result`.

The Query Service constructor needs only the repository contract, the response mapper, and the logger (constructor property promotion). Do not inject `TransactionManagerContract`, `EventDispatcherContract`, `QueueContract`, `SlugCheckerContract`, or `FileUploaderContract` here.

```php
/**
 * Finds one {entity} for the edit form.
 */
public function get{ModuleName}ById(int $id): Result
{
    try {
        ${entity}Entity = $this->{entity}Repository->findById($id);

        if (${entity}Entity === null) {
            return Result::failure(
                message: "{ModuleName} not found with ID: {$id}",
                errorCode: '{MODULE}_NOT_FOUND',
            );
        }

        $responseDTO = $this->responseMapper->toResponseDTO(${entity}Entity);

        return Result::success(
            data: $responseDTO,
            message: "{ModuleName} retrieved successfully.",
        );
    } catch (\Throwable $exception) {
        $this->logger->error(
            "Failed to fetch {module} [ID: {$id}]: " . $exception->getMessage(),
            ['exception' => $exception],
        );

        return Result::failure(
            message: "We couldn't retrieve the {entity} right now. Please try again.",
            errorCode: '{MODULE}_FETCH_ERROR',
        );
    }
}
```

---

## Task 5: Repository Layer Implementation

**Files:** `{ModuleName}RepositoryContract.php` (port) and `{ModuleName}Repository.php` (adapter)

Declare `findById(int $id): ?{ModuleName}Entity` on the contract (only if it is not already declared), then implement it in the repository (the code block is a reference — verify the mapper's `toEntity` signature against CLAUDE.md and the codebase). The method must:

1. **Fetch the raw record** using the internal `findRecord($id)` helper (Eloquent lookup lives here, not in the Service).
2. **Return `null` immediately** if no record is found — do not throw, do not return an empty Entity.
3. **Convert the Model to a Domain Entity** via `$this->mapper->toEntity($model->toArray())` — the Repository must never return a raw Eloquent Model to the Service layer.
4. **Return type must stay `?{ModuleName}Entity`** — nullable, never a bare Entity or a Result object (Result belongs to the Service layer, not the Repository).
5. **Catch framework-specific failures** inside the adapter and rethrow them as a standard `RuntimeException`. Never swallow them silently.

```php
public function findById(int $id): ?{ModuleName}Entity
{
    ${entity}Model = $this->findRecord($id);

    if (!${entity}Model) {
        return null;
    }

    return $this->mapper->toEntity(${entity}Model->toArray());
}
```

---

## Task 6: Response Mapper Implementation

**File:** `{ModuleName}ResponseMapper.php`

Ensure the `toResponseDTO` method in `{ModuleName}ResponseMapper` utilizes `StorageContract` to resolve any file/thumbnail URL fields, if applicable. Follow the exact method signature and mapping convention already defined in CLAUDE.md / the existing codebase — do not invent a new mapper pattern.

- The mapper lives in the Application layer, so it depends on the `StorageContract` **port** only, never on a framework storage class or facade.
- If `StorageContract` has no concrete adapter yet, the Adapter Co-Generation Mandate applies: create the adapter under `core/Shared/Infrastructure/` and bind it once in the composition root (`AppServiceProvider`). Do not call `Storage::url()` or `asset()` inside the mapper.
- Add the resolved URL to the `{ModuleName}ResponseDTO` as a plain string. The ResponseDTO holds plain PHP types only.
- If the module has no file/image fields, leave the mapper untouched.

---

## Task 7: Create Edit View

**File:** `resources/views/backend/pages/{module}/edit.blade.php`

Create the edit view file:

- Build a form pre-filled with the `${entity}` ResponseDTO data (`{FieldList}`), and display any existing file/image URL fields. Use `old('field', ${entity}->field)` so validation failures keep what the user typed.
- Include a CSRF token and the `@method('PUT')` directive for the future update submission.
- **Form Enctype:** If the module involves file/image upload, the form tag must explicitly include `enctype="multipart/form-data"`. Without it, upload requests will not work.
- **Form Action:** Keep the form's `action` attribute as `action="#"` for now, so no `RouteNotFoundException` occurs when rendering the form before the update route is created. Put a visible `{{-- TODO: point to admin.{module}.update when the update feature is built --}}` comment next to it.
- The view reads only ResponseDTO properties. It never calls Entity methods and never contains a business rule.

---

## Task 8: Unit Tests (moved to rule 07 - paused)

Testing is paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim tests. Test requirements live in `.claude/rules/07-testing.txt`, which is loaded only when the developer asks for tests.

---

## Common Pitfalls Checklist (Anti-Hallucination / No-Shortcut Rules)

The agent must self-verify against every item below **by checking the actual generated code**, not from memory. Claim only what the code really contains. A false claim is a defect.

- [ ] **Code snippets in this file are reference implementations, not literal code.** Verify every signature and convention against CLAUDE.md / AGENTS.md and the existing codebase before writing — CLAUDE.md is the style authority, not this template.
- [ ] **No invented method signatures.** Every method called (`findById`, `toResponseDTO`, `Result::failure`, etc.) must match the exact signature already defined elsewhere in the codebase — never assume a signature; check first.
- [ ] **No invented namespaces/imports.** All `use` statements must be verified against the actual class locations, not guessed from naming convention.
- [ ] **Route declared through `Route::resource()`** inside the existing group. No hand-written per-verb edit route.
- [ ] **Edit is a read use case.** The logic is in the Query Service (or the existing single Service for legacy modules), with no transaction, event, queue, or write call.
- [ ] **Controller gets its Service through the constructor** and uses a guard clause on `Result->isFailure()`.
- [ ] **No skipped error handling.** The try/catch and `Result` failure paths must remain exactly as specified — no shortcuts that swallow exceptions or return raw models instead of `Result`.
- [ ] **`errorCode` values are domain keys** (`{MODULE}_...`), never raw HTTP statuses like `'404'`. The generic failure also has its own key.
- [ ] **No layer leakage.** Controller must not touch the Repository directly; Repository must not return raw Models; the Service must not return an Entity. Only ResponseDTOs reach the Controller and view.
- [ ] **No framework import or facade in Core Domain/Application.** Ports use the `...Contract` suffix, and the mapper uses `StorageContract`, not `Storage::` or `asset()`.
- [ ] **Form enctype present when file uploads exist.**
- [ ] **Placeholder route (`action="#"`) used only until the real update route exists** — flagged with a visible TODO, not left silently.
- [ ] **No fabricated field names.** Only use `{FieldList}` fields that actually exist on the entity/migration — verify against the schema before writing view/form code.
- [ ] **CSRF + `@method('PUT')` included** in every edit form without exception.
- Unit tests are paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim them.
- [ ] After adding files under a new namespace path, run `composer dump-autoload`.

---

## Deliverable

Output all updated and newly created files. End with the verification checklist above, filled in only from what the generated code actually contains.
