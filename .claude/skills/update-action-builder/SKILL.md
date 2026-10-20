---
name: update-action-builder
description: Build, trace, and review the end-to-end update flow in Hexagonal Architecture (FormRequest, Request DTO and Mapper, Controller, Command Service with transaction and row lock, Entity behaviour, Repository, view). Enforces Result Pattern, rollback on every early return, file cleanup, events after commit, and the Rich Domain Model.
---

# Update Action Builder Skill

## Description

Build, trace, and review what happens end-to-end when a user clicks "Update" on an Update Form in a Laravel Clean/Hexagonal Architecture project: from the browser request through Middleware, Route, FormRequest, Controller, Mapper, Command Service (transaction, row lock, Entity behaviour), Repository, Model/DB, and back to the response. Use this skill whenever the developer asks to build, review, or debug an update flow for any module, whenever reviewing a Controller/Command Service/Repository trio for an update action, or whenever checking for the known bug classes (missing rollback on early return, TOCTOU race conditions, orphaned uploaded files, Result pattern misuse, events fired before commit, business rules leaking into the Service). Also use when asked to trace or explain "what happens when the update button is clicked."

> **This work follows the Rich Domain Model.** The Entity owns the business rules; the Command Service is an orchestrator only. Update is a **write** use case, so it lives in `{ModuleName}CommandService`, never in a Query Service. Read `AGENTS.md`, and load `.claude/rules/02-framework-adapter-laravel.txt` (route and Eloquent mapping rules), `04-crud-pipeline.txt`, `05-rich-domain-and-services.txt`, `06-ports-and-portability.txt`, and `08-events-and-queues.txt` before building or reviewing.

**Placeholders used in this skill:**

| Placeholder    | Meaning                               | Example                        |
| -------------- | ------------------------------------- | ------------------------------ |
| `{ModuleName}` | PascalCase module name                | `Category`, `Product`, `Brand` |
| `{module}`     | lowercase/plural route & view segment | `categories`, `products`       |
| `{entity}`     | camelCase singular variable name      | `category`, `product`, `brand` |
| `{MODULE}`     | UPPER_SNAKE_CASE error-key prefix     | `CATEGORY`, `PRODUCT`, `BRAND` |

Use this skill for three purposes:

1. **Explaining/tracing** the full lifecycle when asked (e.g. "what happens when the update button is clicked").
2. **Reviewing** a new or existing Controller -> Command Service -> Repository trio against the Known Bug Watchlist below.
3. **Building** a new update flow, following the Build Guidance section.

When reviewing or building, do not change anything outside the request (AGENTS.md - STRICT SCOPE ADHERENCE). Older modules (single `{ModuleName}Service`, no Entity behaviour) keep their current shape unless the prompt asks to migrate them. Review them against the same bug classes and report the architecture gaps without refactoring.

## Quick Reference: Full Lifecycle (top to bottom)

```
Browser (Update button click, form submit)
   │
   ▼
HTTP Request (PUT/PATCH) ──► Route (Route::resource(...)->only([... 'update']))
   │
   ▼
Middleware stack (auth, CSRF, throttle, etc.)
   │
   ▼
FormRequest (e.g. CategoryUpdateRequest)
   — authorize() + validation rules run BEFORE the controller method executes
   │
   ▼
Controller::update(UpdateRequest $request, int $id)
   — thin, orchestration only; Service and Mapper constructor-injected
   — Mapper: plain array -> Request DTO ($this->mapper->mapToUpdateDTO($request->validated()))
   — calls ONE Command Service method, translates Result -> HTTP response (guard clause)
   │
   ▼
CommandService::update{ModuleName}()  ← orchestration only
   1. beginTransaction()
   2. findByIdForUpdate()   — locking read of the existing Entity
   3. derived fields        — slug via SlugGeneratorContract, new file via FileUploaderContract (DTO with*() clones)
   4. Entity behaviour      — business rules run INSIDE the Entity, returns a NEW Entity
   5. repository->update(entity) — persistence (write)
   6. commit() / rollback() — every early return and every catch rolls back
   7. after commit          — delete old/orphaned files, dispatch Domain Events released BEFORE persisting
   │
   ▼
Repository::update() / findByIdForUpdate()
   — Model-facing; translates Entity ⇄ table array through the EntityMapper
   │
   ▼
BaseRepository (findRecordForUpdate / updateRecord)
   — raw Eloquent: lockForUpdate(), fill(), isDirty(), save()
   │
   ▼
Database
   │
   ▼
Result (success/failure) bubbles back up through Command Service -> Controller
   │
   ▼
Controller returns RedirectResponse (back()->with('error') or redirect()->with('success'))
   │
   ▼
Browser re-renders (session flash message shown)
```

## Responsibilities & Known Bug Watchlist Per Layer

Apply the checklist below when reviewing each layer. These come from bugs actually found in real projects.

### 1. FormRequest and Route

- [ ] Validation rules cover presence and format only (`required`, `string`, `max`, `image`, `Rule::in(Enum::values())`). Business invariants (e.g. slug uniqueness) belong in the Entity/Command Service, not the FormRequest.
- [ ] `authorize()` is correctly handled (no missing policy/gate check).
- [ ] The route is declared through `Route::resource()` inside the existing group, with `'update'` in `->only([...])`. No hand-written `Route::put(...)` line.

### 2. Controller

- [ ] **Stays thin:** no business logic, no direct Model/Repository access. Only Mapper -> Command Service -> Result-to-response.
- [ ] **Constructor injection:** the Command Service and `$mapper` (the `...MapperContract`) come through the constructor. `update()` receives only the FormRequest and the route id.
- [ ] The mapper accepts a plain `array` (`$request->validated()`), never the FormRequest.
- [ ] `Result::isFailure()` is checked with a **guard clause**, not a ternary, and both outcomes are handled.
- [ ] **NEVER uses try-catch blocks to catch Domain Exceptions.** The Service catches its own exceptions and returns `Result::failure`. The Controller only evaluates the returned `Result`.
- [ ] LSP check: the Controller does not violate the contract of any abstract base class/interface it implements.

### 3. Command Service (the most important section: most bugs are found here)

- [ ] **No leftover debug statements:** `dd()`, `dump()`, `var_dump()` anywhere.
- [ ] **Every early return inside a transaction rolls back first.** Not-found, domain-exception, and repository-returned-`false` branches each call `rollback()` before `return Result::failure(...)`. A bare early return leaves the transaction open (🔴 Critical).
- [ ] **One dedicated `catch` block per domain exception**, each rolling back and returning `Result::failure` with its own domain key. A domain exception must never fall through to the generic `catch (\Throwable)`. The generic block rolls back, cleans up, logs the exception object, and returns a safe generic failure.
- [ ] **TOCTOU race condition: MANDATORY row-level locking.** The "fetch existing entity" step of `update*()` MUST use a locking read, never a plain read:
    - The Service calls `findByIdForUpdate($id)`, NOT `findById($id)`.
    - `findById()` for that step inside a transaction is a **🔴 Critical violation**: it takes no lock, so a concurrent request can change the row between read and write. A `UNIQUE` index protects only the slug; every other column (price, status, featured image) stays racy.
    - The module's `RepositoryContract` MUST declare `findByIdForUpdate(int $id): ?{ModuleName}Entity`, and the concrete Repository MUST implement it through `BaseRepository::findRecordForUpdate()` (which applies `lockForUpdate()`). Do not re-implement the lock in the module repository. Verify that `findRecordForUpdate()` exists in `BaseRepository`. If it does not, stop and tell the developer, because it is a Shared change.
    - The post-write refresh read (reloading the entity so it contains newly created child rows) MAY use plain `findById()`: the row is already locked at that point.
    - Test requirements for this lock are in `.claude/rules/07-testing.txt` (testing is paused; do not write or require tests).

- [ ] **Business rules live in the Entity.** Rules, state transitions, and derived business values run through an Entity behaviour method that returns a NEW Entity. The Service never reads entity fields to decide (🟡 Design concern). Slug generation that needs a uniqueness check goes through `SlugGeneratorContract` in the Service. The decision about whether a new slug is needed belongs to the Entity.
- [ ] **File cleanup is correct on every branch:**
    - If a new file was uploaded and any later step fails (repository update, domain exception, throwable), the newly uploaded file is deleted (orphan-file bug).
    - On success, the old file is deleted **after** `commit()`. Deleting before commit risks losing the old file if the transaction rolls back.
    - A failing file cleanup after a successful commit is logged, never turned into a failed update.
- [ ] **Events and jobs fire only after commit.** Domain Events are released from the Entity the behaviour method returned, BEFORE persisting (`$events = $updatedEntity->releaseEvents()`), never from the Entity the repository returns (rebuilt from storage, so its list is empty). They go through `EventDispatcherContract` after `commit()`, never before and never on rollback. A failing dispatch after commit is logged and the Result stays success. The Entity never dispatches its own events.
- [ ] **Result Pattern:** every Service method returns `Result` carrying a `{ModuleName}ResponseDTO` (never an Entity or Model). No exception leaks upward.
- [ ] **Domain error keys:** `errorCode` values are keys like `{MODULE}_NOT_FOUND`, `{MODULE}_SLUG_CONFLICT`, never raw HTTP statuses.
- [ ] **Duplicate failure messages/error codes** are DRY'd up through a helper method (e.g. `rollbackAndFail(...)`).
- [ ] The Service stays output-agnostic: it behaves the same whether called from Web, API, Console Command, or a WordPress handler.
- [ ] No framework import, facade, or helper in Core Domain/Application. Ports use the `...Contract` suffix.

### 4. Repository

- [ ] Repository only handles persistence + Entity mapping. No business rule (slug logic, thumbnail logic) has leaked in.
- [ ] It takes and returns **Entities**: `update(Entity): Entity`. It never accepts a DTO or loose array from the Service, and never returns an Eloquent Model.
- [ ] `findById()` / `findByIdForUpdate()` return `null` when the record is missing (the Service decides based on null). Framework-specific failures are caught in the adapter and rethrown as a standard `RuntimeException`, never swallowed.
- [ ] Entity -> table array goes through `EntityMapper::toPersistenceArray()`.
- [ ] If a shared type like `PaginatedResult` is involved, it lives in `Shared\Domain` (avoids Dependency Rule violations). Check this when reviewing listing/index endpoints too.

### 5. BaseRepository

- [ ] `isDirty()` is checked to avoid unnecessary `save()` calls (correct here; keep this pattern in future modules).
- [ ] Mass assignment (`fill()`) has `$fillable`/`$guarded` set properly, otherwise unintended fields can be overwritten.
- [ ] `findRecordForUpdate()` is `protected` and applies `lockForUpdate()`, like the other helpers (framework types must not leak upward).

### 6. View and Route Wiring

- [ ] **View Update Guard:** `edit.blade.php` MUST replace the `action="#"` placeholder with `{{ route('admin.{module}.update', ${entity}->id) }}` once the update route is wired. Keep `@csrf`, `@method('PUT')`, and `enctype="multipart/form-data"` if and only if the schema has file/image columns. Remove any leftover "update route not defined yet" reminder.
- [ ] The view reads only ResponseDTO properties and contains no business rule.

## Build Guidance (when asked to implement the update flow)

Build only what is missing, in this order, and verify every signature against the codebase before writing:

1. **Route:** add `'update'` to the module's `Route::resource()->only([...])`.
2. **FormRequest:** `{ModuleName}UpdateRequest`, format rules only, `authorize()` returns `true`.
3. **Request DTO:** `Application/DTOs/Requests/{ModuleName}UpdateDTO`: a `readonly class` with promoted properties only, **no static factories**, uploaded files typed `mixed`, and a `with*()` clone for each derived field (`withSlug`, `withStoreImage`/`withLogo`).
4. **Request Mapper:** add `mapToUpdateDTO(array $validatedData)` to `{ModuleName}MapperContract` and `{ModuleName}Mapper` (plain array, never a FormRequest).
5. **Entity:** add the behaviour method(s) the update needs. They enforce the rules, throw domain exceptions on violations, and return a new instance.
6. **Repository contract and adapter:** `findByIdForUpdate(int $id): ?Entity` and `update(Entity): Entity`.
7. **Command Service:** `update{ModuleName}(int $id, {ModuleName}UpdateDTO ${entity}UpdateDTO): Result`, shaped like the reference below.
8. **Controller:** append `update()`, using constructor-injected Service and `$mapper`, with a guard clause.
9. **View:** bind the form action as described in section 6.

Reference Command Service flow (verify the real `Result` signature, the Entity behaviour method name, and the port method names before using it):

```php
/**
 * Updates a {entity}.
 */
public function update{ModuleName}(int $id, {ModuleName}UpdateDTO ${entity}UpdateDTO): Result
{
    $uploadedPath = null;

    $this->transactionManager->beginTransaction();

    try {
        ${entity}Entity = $this->{entity}Repository->findByIdForUpdate($id);

        if (${entity}Entity === null) {
            return $this->rollbackAndFail(
                message: "{ModuleName} not found with ID: {$id}",
                errorCode: '{MODULE}_NOT_FOUND',
            );
        }

        // Derived fields (slug, uploaded file) are applied through the DTO's with*() clones.
        // When a file is uploaded, keep its stored path in $uploadedPath for cleanup.

        // Business rules run INSIDE the Entity, which returns a NEW instance.
        $updated{ModuleName}Entity = ${entity}Entity->applyChanges(/* from the DTO */);

        $events = $updated{ModuleName}Entity->releaseEvents(); // BEFORE persisting

        $saved{ModuleName}Entity = $this->{entity}Repository->update($updated{ModuleName}Entity);

        $this->transactionManager->commit();
    } catch ({ModuleName}SlugConflictException $exception) {
        return $this->rollbackAndFail(
            message: $exception->getMessage(),
            errorCode: '{MODULE}_SLUG_CONFLICT',
            uploadedPath: $uploadedPath,
        );
    } catch (\Throwable $exception) {
        $this->logger->error(
            "Failed to update {module} [ID: {$id}]: " . $exception->getMessage(),
            ['exception' => $exception],
        );

        return $this->rollbackAndFail(
            message: "We couldn't update the {ModuleName} right now. Please try again.",
            errorCode: '{MODULE}_UPDATE_ERROR',
            uploadedPath: $uploadedPath,
        );
    }

    // Committed. Side effects below are logged on failure and never undo the update.
    $this->removeOldFileQuietly(/* old path, only if a new file replaced it */);
    $this->dispatchEventsQuietly($events);

    return Result::success(
        data: $this->responseMapper->toResponseDTO($saved{ModuleName}Entity),
        message: "{ModuleName} updated successfully.",
    );
}

private function rollbackAndFail(string $message, string $errorCode, ?string $uploadedPath = null): Result
{
    $this->transactionManager->rollback();
    $this->removeFileQuietly($uploadedPath); // delete a newly uploaded, now-orphaned file

    return Result::failure(message: $message, errorCode: $errorCode);
}

/** @param list<object> $events */
private function dispatchEventsQuietly(array $events): void
{
    try {
        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    } catch (\Throwable $exception) {
        $this->logger->error('Could not dispatch {module} update events: ' . $exception->getMessage());
    }
}
```

## Review Workflow

1. Read files in this order: Controller -> Command Service -> Entity -> Repository -> BaseRepository -> View.
2. Check each layer against its checklist above, paying special attention to the **Command Service's transaction/rollback, row-lock, file-cleanup, and event sections**, where most bugs are found.
3. Report findings by severity:
    - 🔴 **Critical** (data integrity/transaction bug): early return without rollback, `findById()` instead of `findByIdForUpdate()` inside the transaction, domain exception falling through to the generic catch, orphaned or prematurely deleted file, events dispatched before commit, events released from the repository-returned Entity (always empty), a cleanup failure reported as a failed update.
    - 🟡 **Design concern:** business rule in the Service instead of the Entity, DRY violations, Entity/Model returned from the Service, ternary instead of guard clause, raw HTTP status in `errorCode`, framework import in Core.
4. Tests are paused (`AGENTS.md` - UNIT TESTING POLICY): do not review, write, or require them.
5. If the same bug class could plausibly occur in another module (Auth, Product, etc.), flag it and suggest adding it to this skill's Known Bug Watchlist.

## Notes

- This skill is project-specific: it assumes a Clean/Hexagonal Architecture (Domain/Application/Infrastructure layers, Rich Domain Model, Command/Query Service split, Result Pattern, `PaginatedResult` in `Shared\Domain`).
- When a new bug class is found, add it to the Known Bug Watchlist above and update this skill, so future reviews don't miss it again.
- Report only what you verified in the actual code. A review claim or checklist tick that the code does not support is a defect.
