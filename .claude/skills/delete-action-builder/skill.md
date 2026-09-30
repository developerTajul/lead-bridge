# Task: Implement `{ModuleName}` Delete Feature

Implement the `{ModuleName}` Delete feature based on the following specifications. Ensure strict adherence to our Hexagonal Architecture.

> **On the code snippets below:** each one is a _reference implementation_ showing the expected logic, structure, and step order — it is **not** a literal drop-in. Before writing any method, verify the exact signatures (`Result`, mapper methods, logger calls, etc.) and style conventions against `CLAUDE.md` and the existing codebase. If the real signature or convention differs from the reference, follow the real one — never the placeholder.

**Placeholders used in this template:**

| Placeholder    | Meaning                               | Example                            |
| -------------- | ------------------------------------- | ---------------------------------- |
| `{ModuleName}` | PascalCase entity name                | `Category`, `Product`, `Brand`     |
| `{module}`     | lowercase/plural route & path segment | `categories`, `products`, `brands` |
| `{entity}`     | camelCase variable name               | `category`, `product`, `brand`     |

---

## Task 1: Routing

Ensure a `DELETE` route exists for the destroy action.

| Key               | Value                            |
| ----------------- | -------------------------------- |
| Route             | `/admin/{module}/{{{entity}}}`   |
| Name              | `admin.{module}.destroy`         |
| Controller Action | `{ModuleName}Controller@destroy` |

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

---

## Task 3: Controller Implementation

**File:** `{ModuleName}Controller.php`

> **Namespace/Imports note:** ensure the controller imports `RedirectResponse` and `Result` correctly — do not invent or assume a namespace; verify it against the existing codebase.

Implement the `destroy` method following the logic below. The method must:

- **Delegate to the Service layer** — call `{entity}Service->delete{ModuleName}($id)`. The Controller must never talk to the Repository directly.
- **Handle the `Result`** — redirect back to `admin.{module}.index`. Flash a success message if `$result->isSuccess()`, or an error message if `$result->isFailure()`, extracting the message directly from the `Result` object.
- **Never catch exceptions here** — error handling belongs to the Service layer; the Controller only reacts to the `Result` object.

```php
public function destroy(int $id): RedirectResponse
{
    $result = $this->{entity}Service->delete{ModuleName}($id);

    if ($result->isFailure()) {
        return redirect()->route('admin.{module}.index')->with('error', $result->message);
    }

    return redirect()->route('admin.{module}.index')->with('success', $result->message);
}
```

---

## Task 4: Service Layer Implementation

**File:** `{ModuleName}Service.php`

Implement the `delete{ModuleName}` method following the logic below. The method must:

1. **Fetch the entity first** — call `{entity}Repository->findById($id)` to ensure it exists before attempting deletion (and to retrieve file paths if file cleanup is necessary).
2. **Handle the not-found case** — if the repository returns nothing, return `Result::failure()` with a `404` error code.
3. **Execute deletion** — call `{entity}Repository->delete($id)`.
4. **File cleanup (if applicable)** — if the entity contains files/images (e.g. thumbnails), use the `StorageInterface`/`FileUploader` to delete the associated files from disk **only after** successful database deletion (never before — if deletion later fails for some reason, you'd have deleted a file the DB row still references).
5. **Catch unexpected errors** — wrap the whole flow in a `try`/`catch`. On `\Throwable`, log the error via `$this->logger->error()`, then return a generic `Result::failure()` with its own error code.

> **Note on transactions:** this reference has no `transactionManager` wrapping, which is correct for a simple single-row delete. If `{ModuleName}` has child records that must be deleted together (cascade), or any multi-step DB write beyond the single `delete()` call, wrap steps 1–3 in `beginTransaction()` / `commit()` / `rollback()` — follow the same domain-exception pattern used in the Update flow (dedicated `catch` block per domain exception, never let it fall through to a generic `catch (\Throwable)`), don't reintroduce the early-return-without-rollback bug found there.

```php
public function delete{ModuleName}(int $id): Result
{
    try {
        ${entity}Entity = $this->{entity}Repository->findById($id);

        if (!${entity}Entity) {
            return Result::failure(
                message: "{ModuleName} not found with ID: {$id}",
                errorCode: '404',
            );
        }

        $isDeleted = $this->{entity}Repository->delete($id);

        if (!$isDeleted) {
            return Result::failure(
                message: "Failed to delete the {ModuleName}. It might be in use.",
                errorCode: 'DELETE_FAILED',
            );
        }

        // File cleanup — only after confirmed DB deletion, and only if this
        // entity actually has a file/image field. Omit this block entirely
        // for entities with no such field.
        if (${entity}Entity->thumbnail !== null) {
            $this->fileUploader->delete(${entity}Entity->thumbnail);
        }

        return Result::success(
            data: null,
            message: "{ModuleName} deleted successfully.",
        );
    } catch (\Throwable $exception) {
        $this->logger->error(
            "Failed to delete {module} [ID: {$id}]: " . $exception->getMessage(),
            ['exception' => $exception],
        );

        return Result::failure(
            message: "We couldn't delete the {ModuleName} right now. Please try again.",
            errorCode: '{MODULE}_DELETE_FAILED',
        );
    }
}
```

---

## Task 5: Repository Layer Implementation

**File:** `{ModuleName}Repository.php`

Implement the `delete` method. The method must:

- Fetch the raw record using the internal `findRecord($id)` helper.
- Return `false` immediately if no record is found.
- Execute Eloquent `delete()` on the model.
- Return a boolean (`true` on success, `false` on failure). Do **not** return `Result` objects from the repository.

```php
public function delete(int $id): bool
{
    ${entity}Model = $this->findRecord($id);

    if (!${entity}Model) {
        return false;
    }

    return (bool) ${entity}Model->delete();
}
```

---

## Common Pitfalls Checklist (Anti-Hallucination / No-Shortcut Rules)

The agent must self-verify against every item below before marking this task complete:

- [ ] Code snippets in this file are reference implementations, not literal code. Verify every signature and convention against `CLAUDE.md` and the existing codebase before writing.
- [ ] No `<a>` tags for deletion. The delete action in the frontend must use a `<form>` with `@method('DELETE')` and `@csrf`.
- [ ] Mandatory confirmation popup. The frontend form must trigger _"Are you sure to delete this data?"_ before submitting.
- [ ] No bypassed validation in Service. The Service must check if the entity exists before issuing a delete command to the repository.
- [ ] No layer leakage. Controller must not touch the Repository directly; Repository must only return booleans or raw data, not `Result` objects.
- [ ] No skipped file cleanup. If the entity has an associated image/file, it must actually be removed from storage upon successful database deletion — not left as a `TODO` comment.
- [ ] File cleanup happens strictly _after_ confirmed successful deletion, never before.
- [ ] No raw exception exposure. The `try`/`catch` in the Service layer must catch `\Throwable` and return a safe `Result::failure()` message with its own error code.
- [ ] If this module has child records requiring cascade deletion, the multi-step delete is wrapped in a transaction with per-domain-exception `catch` blocks — the early-return-without-rollback bug from the Update flow must not be reintroduced here.

---

## Deliverable

Output all updated and newly created files.
