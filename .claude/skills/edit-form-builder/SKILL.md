# Task: Implement `{ModuleName}` Edit Form Feature

Implement the `{ModuleName}` Edit Form feature based on the following specifications. **Ensure strict adherence to our Hexagonal Architecture.**

> **On the code snippets below:** each one is a **reference implementation** showing the expected logic, structure, and step order — it is not a literal drop-in. Before writing any method, verify the exact signatures (`Result`, mapper methods, logger calls, etc.) and style conventions against **CLAUDE.md** and the existing codebase. If the real signature or convention differs from the reference, follow the real one — never the placeholder.

> **Placeholders used in this template:**
> - `{ModuleName}` — PascalCase entity name (e.g. `Category`, `Product`, `Brand`)
> - `{module}` — lowercase/plural route & path segment (e.g. `categories`, `products`, `brands`)
> - `{entity}` — camelCase variable name (e.g. `category`, `product`, `brand`)
> - `{FieldList}` — the entity's editable fields (e.g. `name, slug, status`)

---

## Task 1: Routing

Ensure a `GET` route exists for the edit action.

| Key | Value |
|---|---|
| Route | `/admin/{module}/{{{entity}}}/edit` |
| Name | `admin.{module}.edit` |
| Controller Action | `{ModuleName}Controller@edit` |

---

## Task 2: Index View Update

**File:** `resources/views/backend/pages/{module}/index.blade.php`

Update the "Edit" button in the index view table. Change the `href` attribute from `"#"` to point to the `admin.{module}.edit` route, passing the `{entity}` ID.

---

## Task 3: Controller Implementation

**File:** `{ModuleName}Controller.php`

> **Namespace/Imports Note:** Ensure the controller imports `View`, `RedirectResponse`, and `Result` correctly — do not invent or assume a namespace; verify it against the existing codebase.

Implement the `edit` method following the logic below (the code block is a reference — verify exact `Result`/return-type signatures against CLAUDE.md and the codebase). The method must:

1. **Delegate to the Service layer** — call `{entity}Service->get{ModuleName}ById($id)`. The Controller must never talk to the Repository directly.
2. **Check for failure** using `$result->isFailure()`. On failure, redirect back to `admin.{module}.index` with a flashed `error` message taken from `$result->message` — do not construct a custom error message here.
3. **Extract the data** from `$result->data` only after confirming success.
4. **Return the edit view**, passing the `{entity}` data via `compact()`.
5. **Never catch exceptions here** — error handling belongs to the Service layer; the Controller only reacts to the `Result` object.

```php
public function edit(int $id): View|RedirectResponse
{
    $result = $this->{entity}Service->get{ModuleName}ById($id);

    if ($result->isFailure()) {
        return redirect()->route('admin.{module}.index')->with('error', $result->message);
    }

    ${entity} = $result->data;

    return view('backend.pages.{module}.edit', compact('{entity}'));
}
```

---

## Task 4: Service Layer Implementation

**File:** `{ModuleName}Service.php`

Implement the `get{ModuleName}ById` method following the logic below (the code block is a reference — verify `Result::failure`/`Result::success` signatures, mapper method name, and logger call format against CLAUDE.md and the codebase). The method must:

1. **Fetch the entity** by calling `{entity}Repository->findById($id)`.
2. **Handle the not-found case:** if the repository returns nothing (`null`/falsy), return `Result::failure()` with a `404` error code and a message stating the `{ModuleName}` was not found for that ID. Do not throw an exception for this case — it is an expected business outcome, not a system error.
3. **Map the entity to a response DTO** using `$this->mapper->toResponseDTO()`, so the Service never leaks Domain Entities to the Controller/View layer.
4. **Return success:** wrap the DTO in `Result::success()` with a clear success message.
5. **Catch unexpected errors:** wrap the whole flow in a try/catch. On `\Throwable`, log the error (with the exception object, not just the message string) via `$this->logger->error()`, then return a generic `Result::failure()` message — never expose raw exception details to the end user.
6. **Never return the raw Entity or Model directly** — the only valid return type from this method is `Result`.

```php
public function get{ModuleName}ById(int $id): Result
{
    try {
        ${entity}Entity = $this->{entity}Repository->findById($id);

        if (!${entity}Entity) {
            return Result::failure(
                message: "{ModuleName} not found with ID: {$id}",
                errorCode: '404'
            );
        }

        $responseDTO = $this->mapper->toResponseDTO(${entity}Entity);

        return Result::success(
            data: $responseDTO,
            message: "{ModuleName} retrieved successfully."
        );

    } catch (\Throwable $e) {
        $this->logger->error("Failed to fetch {module} [ID: {$id}]: " . $e->getMessage(), ['exception' => $e]);

        return Result::failure(
            message: "We couldn't retrieve the {entity} right now. Please try again."
        );
    }
}
```

---

## Task 5: Repository Layer Implementation

**File:** `{ModuleName}Repository.php`

Implement the `findById` method following the logic below (the code block is a reference — verify the mapper's `toEntity` signature against CLAUDE.md and the codebase). The method must:

1. **Fetch the raw record** using the internal `findRecord($id)` helper (Eloquent lookup lives here, not in the Service).
2. **Return `null` immediately** if no record is found — do not throw, do not return an empty Entity.
3. **Convert the Model to a Domain Entity** via `$this->mapper->toEntity($model->toArray())` — the Repository must never return a raw Eloquent Model to the Service layer.
4. **Return type must stay `?{ModuleName}Entity`** — nullable, never a bare Entity or a Result object (Result belongs to the Service layer, not the Repository).

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

## Task 6: Mapper Implementation

**File:** `{ModuleName}ResponseMapper.php`

Ensure the `toResponseDTO` method in `{ModuleName}ResponseMapper` utilizes the `StorageInterface` to resolve any file/thumbnail URL fields, if applicable. Follow the exact method signature and mapping convention already defined in CLAUDE.md / the existing codebase — do not invent a new mapper pattern.

---

## Task 7: Create Edit View

**File:** `resources/views/backend/pages/{module}/edit.blade.php`

Create the edit view file:

- Build a form pre-filled with the `${entity}` data (`{FieldList}`), and display any existing file/image URL fields.
- Include a CSRF token and the `@method('PUT')` directive for the future update submission.
- **Form Enctype:** If the module involves file/image upload, the form tag must explicitly include `enctype="multipart/form-data"`. Without it, upload requests will not work.
- **Form Action:** Keep the form's `action` attribute as `action="#"` for now, so no `RouteNotFoundException` occurs when rendering the form before the update route is created.

---

## Common Pitfalls Checklist (Anti-Hallucination / No-Shortcut Rules)

The agent must self-verify against every item below before marking this task complete:

- [ ] **Code snippets in this file are reference implementations, not literal code.** Verify every signature and convention against CLAUDE.md and the existing codebase before writing — CLAUDE.md is the style authority, not this template.
- [ ] **No invented method signatures.** Every method called (`findById`, `toResponseDTO`, `Result::failure`, etc.) must match the exact signature already defined elsewhere in the codebase — never assume a signature; check first.
- [ ] **No invented namespaces/imports.** All `use` statements must be verified against the actual class locations, not guessed from naming convention.
- [ ] **No skipped error handling.** The try/catch and `Result` failure paths must remain exactly as specified — no shortcuts that swallow exceptions or return raw models instead of `Result`.
- [ ] **No layer leakage.** Controller must not touch the Repository directly; Repository must not return raw Models to the Service/Controller layer — only Entities/DTOs cross boundaries.
- [ ] **Form enctype present when file uploads exist.**
- [ ] **Placeholder route (`action="#"`) used only until the real update route exists** — must be flagged as a TODO, not left silently.
- [ ] **No fabricated field names.** Only use `{FieldList}` fields that actually exist on the entity/migration — verify against the schema before writing view/form code.
- [ ] **CSRF + `@method('PUT')` included** in every edit form without exception.

---

## Deliverable

Output all updated and newly created files.
