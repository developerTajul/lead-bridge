# Task: Implement `{ModuleName}` Delete Feature

Implement the `{ModuleName}` Delete feature based on the following specifications. Ensure strict adherence to our Hexagonal Architecture.

> **This work follows the Rich Domain Model.** Business rules (for example "can this record be deleted?") live in the Entity, not in the Service. The Service is an orchestrator only. Read `AGENTS.md`, and load `.claude/rules/05-rich-domain-and-services.txt` and `.claude/rules/06-ports-and-portability.txt` before writing code.

> **On the code snippets below:** each one is a _reference implementation_ showing the expected logic, structure, and step order — it is **not** a literal drop-in. Before writing any method, verify the exact signatures (`Result`, mapper methods, logger calls, etc.) and style conventions against `CLAUDE.md` / `AGENTS.md` and the existing codebase. If the real signature or convention differs from the reference, follow the real one — never the placeholder.

**Placeholders used in this template:**

| Placeholder    | Meaning                               | Example                            |
| -------------- | ------------------------------------- | ---------------------------------- |
| `{ModuleName}` | PascalCase entity name                | `Category`, `Product`, `Brand`     |
| `{module}`     | lowercase/plural route & path segment | `categories`, `products`, `brands` |
| `{entity}`     | camelCase variable name               | `category`, `product`, `brand`     |
| `{MODULE}`     | UPPER_SNAKE_CASE error-key prefix     | `CATEGORY`, `PRODUCT`, `BRAND`     |

---

## Architecture Rules for This Task (read first)

1. **Rich Domain Model.** The Entity owns every business rule. If deleting is conditional (in use, has active children, wrong status), that check is a method on the Entity. The Service never reads entity fields to decide.
2. **Command Service.** Delete is a write use case. It goes in `{ModuleName}CommandService`, never in a Query Service.
3. **Service is an orchestrator.** It loads the Entity, asks the Entity, persists through the repository port, then handles side effects (file cleanup, events).
4. **Entity-based repository.** The repository receives the Entity, not loose arrays or data objects.
5. **Ports only.** Core code (Domain and Application) uses `...Contract` ports (`FileUploaderContract`, `EventDispatcherContract`, `TransactionManagerContract`) and imports nothing from Laravel or WordPress.
6. **Domain error keys.** `Result` `errorCode` holds domain-specific keys (e.g. `{MODULE}_NOT_FOUND`), never raw HTTP statuses like `'404'`. The Controller translates keys to responses.
7. **Legacy modules.** If `{ModuleName}` is an older module (single `{ModuleName}Service`, no `{ModuleName}CommandService`, no Entity behaviour), add the delete method to the existing Service and skip the Entity-behaviour and event steps unless the prompt explicitly asks to migrate it. Never refactor beyond the task. All other rules in this file still apply.

---

## Task 1: Routing

Ensure a `DELETE` route exists for the destroy action. Routes are declared with `Route::resource()` inside the existing middleware/prefix/name group — **do not** hand-write a per-verb line.

| Key               | Value                            |
| ----------------- | -------------------------------- |
| Route             | `/admin/{module}/{{{entity}}}`   |
| Name              | `admin.{module}.destroy`         |
| Controller Action | `{ModuleName}Controller@destroy` |

```php
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('{module}', {ModuleName}Controller::class);
});
```

If the module's resource is already registered with `->only([...])`, add `'destroy'` to it. If it uses `->except([...])`, remove `'destroy'` from it.

---

## Task 2: Index View Update (Frontend Confirmation)

**File:** `resources/views/backend/pages/{module}/index.blade.php`

Update the "Delete" button in the index view table. It must **not** be a simple `<a>` tag. It must be a discrete `<form>` submission using the `@method('DELETE')` directive.

**Confirmation Popup:** Intercept the form submission (via inline JS `onsubmit`, or Alpine.js/jQuery depending on the project stack) to display a native or custom popup asking exactly: _"Are you sure to delete this data?"_. If the user cancels the popup, the form submission must be aborted.

```html
<form
    action="{{ route('admin.{module}.destroy', ${entity}->id) }}"
    method="POST"
    onsubmit="return confirm('Are you sure to delete this data?');"
    style="display:inline-block;"
>
    @csrf @method('DELETE')
    <button type="submit" class="btn btn-danger">Delete</button>
</form>
```

`${entity}` in the view is the `{ModuleName}ResponseDTO` the index already receives. Do not change what the index passes to the view.

---

## Task 3: Controller Implementation

**File:** `{ModuleName}Controller.php`

> **Namespace/Imports note:** ensure the controller imports `RedirectResponse` and `Result` correctly — do not invent or assume a namespace; verify it against the existing codebase.

Implement the `destroy` method following the logic below. The method must:

- **Delegate to the Command Service** — call `${entity}CommandService->delete{ModuleName}($id)`. The Controller must never talk to the Repository directly.
- **Receive dependencies through the constructor.** `{ModuleName}CommandService` is constructor-injected (with constructor property promotion). `destroy()` takes only the route id.
- **Handle the `Result`** — redirect back to `admin.{module}.index`. Flash an error message if `$result->isFailure()`, otherwise a success message, extracting the message directly from the `Result` object.
- **Use a guard clause**, not a ternary.
- **Never catch exceptions here** — error handling belongs to the Service layer; the Controller only reacts to the `Result` object.

```php
public function destroy(int $id): RedirectResponse
{
    $result = $this->{entity}CommandService->delete{ModuleName}($id);

    if ($result->isFailure()) {
        return redirect()->route('admin.{module}.index')->with('error', $result->message);
    }

    return redirect()->route('admin.{module}.index')->with('success', $result->message);
}
```

---

## Task 4: Domain Layer (Entity Behaviour, Exception, Event)

**Files:**

- `core/{ModuleName}/Domain/Entities/{ModuleName}Entity.php`
- `core/{ModuleName}/Domain/Exceptions/{ModuleName}CannotBeDeletedException.php`
- `core/{ModuleName}/Domain/Events/{ModuleName}DeletedEvent.php` (only if other parts of the system react to deletion)

**When to add what (no speculative rules):**

- The module has a real deletion rule (in use, has children, protected status) → add `markAsDeleted()` that enforces it and throws `{ModuleName}CannotBeDeletedException`.
- Something else reacts to the deletion (cache, audit, cleanup job) → the Entity also carries a `{ModuleName}DeletedEvent`.
- Neither applies → skip this task and have the Service call the repository directly after the existence check. Do not invent a rule the business does not have.

The Entity is `readonly`. `markAsDeleted()` enforces the rule and returns a **new** instance. It never mutates `$this` and never dispatches the event itself. It must not import anything from a framework.

```php
/**
 * Checks the delete rules and returns the entity with its deleted event.
 *
 * @throws {ModuleName}CannotBeDeletedException
 */
public function markAsDeleted(): self
{
    // TODO: replace with the module's real rule, e.g.
    // if ($this->isInUse) { throw new {ModuleName}CannotBeDeletedException(...); }

    return new self(
        // pass every constructor property by name, same values as $this,
        domainEvents: [...$this->domainEvents, new {ModuleName}DeletedEvent(id: $this->id)],
    );
}
```

The event is a plain PHP class (no framework base class) carrying plain data only:

```php
readonly class {ModuleName}DeletedEvent
{
    public function __construct(public ?int $id) {}
}
```

---

## Task 5: Command Service Implementation

**File:** `{ModuleName}CommandService.php`

Implement the `delete{ModuleName}` method following the logic below. The method must:

1. **Fetch the entity first** — call `{entity}Repository->findById($id)` to ensure it exists before attempting deletion (and to retrieve file paths if file cleanup is necessary).
2. **Handle the not-found case** — if the repository returns nothing, return `Result::failure()` with the error key `{MODULE}_NOT_FOUND`.
3. **Ask the Entity** — call `markAsDeleted()` (when Task 4 applies). Catch `{ModuleName}CannotBeDeletedException` in its **own** `catch` block and convert it to `Result::failure()` with `{MODULE}_CANNOT_BE_DELETED`.
4. **Execute deletion** — call `{entity}Repository->delete($entity)`. If it returns `false`, return `Result::failure()` with `{MODULE}_DELETE_FAILED`.
5. **File cleanup (if applicable)** — if the entity has files/images (e.g. thumbnails), delete them through `FileUploaderContract` **only after** the database deletion is confirmed (never before — if the deletion later failed, you would have removed a file the DB row still references).
6. **Cleanup and events must not undo the delete.** Once the row is gone the use case succeeded. File cleanup and event dispatch each run in their own `try`/`catch`; a failure there is **logged** and the method still returns success. Never let those failures reach the generic `catch` and report a failed delete for a row that no longer exists.
7. **Dispatch released Domain Events** through `EventDispatcherContract` **after** the delete is confirmed (after `commit()` when a transaction is used). Skip this step when the module has no events.
8. **Catch unexpected errors** — wrap the main flow in a `try`/`catch`. On `\Throwable`, log via `$this->logger->error()`, then return a generic `Result::failure()` with `{MODULE}_DELETE_ERROR`. Never expose the raw exception message.
9. **Return `Result`** with `data: null` — delete has no payload, so there is no ResponseDTO to carry.

> **Note on transactions:** this reference has no `transactionManager` wrapping, which is correct for a simple single-row delete. If `{ModuleName}` has child records that must be deleted together (cascade), or any multi-step DB write beyond the single `delete()` call, wrap steps 1–4 in `beginTransaction()` / `commit()` / `rollback()`. Use a dedicated `catch` block per domain exception, each calling `rollback()` before returning (as in the Update flow). Never let a domain exception fall through to the generic `catch (\Throwable)`, and never return early without rolling back. File cleanup and events then run **after** `commit()`.

```php
/**
 * Deletes a {entity}.
 */
public function delete{ModuleName}(int $id): Result
{
    try {
        ${entity}Entity = $this->{entity}Repository->findById($id);

        if (${entity}Entity === null) {
            return Result::failure(
                message: "{ModuleName} not found with ID: {$id}",
                errorCode: '{MODULE}_NOT_FOUND',
            );
        }

        // Business rule lives in the Entity. Omit if the module has no delete rule/event.
        $deleted{ModuleName}Entity = ${entity}Entity->markAsDeleted();

        $isDeleted = $this->{entity}Repository->delete($deleted{ModuleName}Entity);

        if (!$isDeleted) {
            return Result::failure(
                message: "Failed to delete the {ModuleName}. It might be in use.",
                errorCode: '{MODULE}_DELETE_FAILED',
            );
        }
    } catch ({ModuleName}CannotBeDeletedException $exception) {
        return Result::failure(
            message: $exception->getMessage(),
            errorCode: '{MODULE}_CANNOT_BE_DELETED',
        );
    } catch (\Throwable $exception) {
        $this->logger->error(
            "Failed to delete {module} [ID: {$id}]: " . $exception->getMessage(),
            ['exception' => $exception],
        );

        return Result::failure(
            message: "We couldn't delete the {ModuleName} right now. Please try again.",
            errorCode: '{MODULE}_DELETE_ERROR',
        );
    }

    // The row is gone. Side effects below can log, but never turn this into a failure.
    $this->removeFileQuietly(${entity}Entity->thumbnail); // omit if no file/image field
    $this->dispatchEventsQuietly($deleted{ModuleName}Entity); // omit if no events

    return Result::success(
        data: null,
        message: "{ModuleName} deleted successfully.",
    );
}

private function removeFileQuietly(?string $path): void
{
    if ($path === null) {
        return;
    }

    try {
        $this->fileUploader->delete($path);
    } catch (\Throwable $exception) {
        $this->logger->warning("Could not remove file [{$path}] after {module} delete: " . $exception->getMessage());
    }
}

private function dispatchEventsQuietly({ModuleName}Entity ${entity}Entity): void
{
    try {
        foreach (${entity}Entity->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    } catch (\Throwable $exception) {
        $this->logger->error("Could not dispatch {module} delete events: " . $exception->getMessage());
    }
}
```

The Command Service constructor must receive `FileUploaderContract` (when the module has a file/image column) and `EventDispatcherContract` (when the module raises events), through constructor property promotion. If an adapter for a newly injected port is missing from `core/Shared/Infrastructure/`, create it.

---

## Task 6: Repository Layer Implementation

**Files:** `{ModuleName}RepositoryContract.php` (port) and `{ModuleName}Repository.php` (adapter)

Declare `delete({ModuleName}Entity ${entity}): bool` on the contract, then implement it in the repository. The method must:

- Return `false` immediately if the entity has no id.
- Fetch the raw record using the internal `findRecord($id)` helper.
- Return `false` immediately if no record is found.
- Execute Eloquent `delete()` on the model.
- Return a boolean (`true` on success, `false` on failure). Do **not** return `Result` objects from the repository.
- Catch framework-specific failures and rethrow them as a standard `RuntimeException`. Never swallow them silently.

```php
public function delete({ModuleName}Entity ${entity}Entity): bool
{
    if (${entity}Entity->id === null) {
        return false;
    }

    ${entity}Model = $this->findRecord(${entity}Entity->id);

    if (!${entity}Model) {
        return false;
    }

    return (bool) ${entity}Model->delete();
}
```

---

## Task 7: Unit Tests (moved to rule 07 - paused)

Testing is paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim tests. Test requirements live in `.claude/rules/07-testing.txt`, which is loaded only when the developer asks for tests.

---

## Common Pitfalls Checklist (Anti-Hallucination / No-Shortcut Rules)

The agent must self-verify against every item below **by checking the actual generated code**, not from memory. Claim only what the code really contains. A false claim is a defect.

- [ ] Code snippets in this file are reference implementations, not literal code. Verify every signature and convention against `CLAUDE.md` / `AGENTS.md` and the existing codebase before writing.
- [ ] Route is declared through `Route::resource()` inside the existing group. No hand-written per-verb delete route.
- [ ] No `<a>` tags for deletion. The delete action in the frontend uses a `<form>` with `@method('DELETE')` and `@csrf`.
- [ ] Mandatory confirmation popup. The frontend form triggers _"Are you sure to delete this data?"_ before submitting.
- [ ] Controller uses a guard clause on `Result->isFailure()`, not a ternary, and gets the Command Service through its constructor.
- [ ] Delete logic is in the Command Service, not a Query Service.
- [ ] Business rules live in the Entity. The Service never reads entity fields to decide whether deletion is allowed.
- [ ] Entity stays `readonly`; `markAsDeleted()` returns a new instance and never dispatches the event.
- [ ] No bypassed validation in Service. The Service checks that the entity exists before issuing a delete command to the repository.
- [ ] `errorCode` values are domain keys (`{MODULE}_...`), never raw HTTP statuses like `'404'`.
- [ ] No layer leakage. Controller does not touch the Repository; Repository returns only booleans or Domain types, never `Result` objects.
- [ ] Repository `delete()` takes the Entity, not a loose array or data object.
- [ ] No framework import or facade in Core Domain/Application. Ports use the `...Contract` suffix.
- [ ] No skipped file cleanup. If the entity has an associated image/file, it is actually removed from storage after a successful database deletion — not left as a `TODO` comment.
- [ ] File cleanup and event dispatch happen strictly _after_ confirmed deletion (after `commit()` when transactional), never before.
- [ ] A failing file cleanup or event dispatch is logged and does not turn a completed delete into a failure.
- [ ] No raw exception exposure. The `try`/`catch` in the Service catches `\Throwable` and returns a safe `Result::failure()` with its own error key.
- [ ] If this module has child records requiring cascade deletion, the multi-step delete is wrapped in a transaction with per-domain-exception `catch` blocks that roll back — the early-return-without-rollback bug from the Update flow must not be reintroduced here.
- Unit tests are paused (`AGENTS.md` - UNIT TESTING POLICY). Do not write or claim them.
- [ ] After adding files under a new namespace path, run `composer dump-autoload`.

---

## Deliverable

Output all updated and newly created files. End with the verification checklist above, filled in only from what the generated code actually contains.
