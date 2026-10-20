---
name: form-builder
description: Scaffolds the delivery layer for a module's create form (route, thin controller create action, Blade form) strictly from a provided database schema. Use when asked to build or scaffold a create/add form for a module. Does not implement store or persistence logic.
---

# Form Builder Skill (Delivery Layer Scaffolding)

## Description

This skill generates the Laravel delivery layer needed to render a create form, based STRICTLY on a provided database schema. It follows the request lifecycle (Route -> Controller -> View) and prepares the user interface gateway without implementing any data-persistence logic.

> **This work follows the Rich Domain Model.** The form scaffold is delivery code only: it holds no business rules and touches no Entity, Service, or Repository. Business rules (validation of state, derived values) belong to the Entity and arrive later with the store use case. Read `AGENTS.md` and `.claude/rules/02-framework-adapter-laravel.txt` (route rules) before writing code.

**Placeholders used in this skill:**

| Placeholder    | Meaning                               | Example                        |
| -------------- | ------------------------------------- | ------------------------------ |
| `{ModuleName}` | PascalCase module name                | `Category`, `Product`, `Brand` |
| `{module}`     | lowercase/plural route & path segment | `categories`, `products`       |

## Context & Rules

- **Source of Truth:** You MUST analyze the provided SQL database schema or field list exactly. Explicitly skip primary keys (e.g., `id`), auto-managed timestamps (e.g., `created_at`, `updated_at`), soft deletes (e.g., `deleted_at`), and domain-generated fields (e.g., `slug`, `uuid`, `token`). Do not invent or assume extra fields outside of this context. If a column type is not covered by the mapping table below, do not guess: render a plain text input and flag it in a comment for the developer.
- **Empty-Skeleton Guard:** The controller acts only as a view renderer at this stage. Do NOT write `store()`, a FormRequest, a Service call, a mapper call, or any data-processing logic yet.
- **Delivery layer only:** Everything generated lives under `app/` and `resources/`. Do not create or edit anything under `core/` (no Entity, Service, Repository, or DTO).
- **No business logic in the view or controller:** no rules, no calculations, no Entity or Repository access.
- **Form Method Policy:** Forms must strictly use the POST method, preparing them for future data submission. Do not add `@method(...)`; that is only for PUT/PATCH/DELETE forms.
- **Scope:** Create or edit only the files listed in the Execution Steps. If something outside that list seems necessary, stop and ask first (AGENTS.md - STRICT SCOPE ADHERENCE).
- **Verify before writing:** Check the existing views directory and layout, controller namespace, and route file for the project's real conventions. Follow what exists; never guess a namespace or path.

## Execution Steps

1. **Route Architecture Wiring (`routes/web.php`):**
    - Routes are declared with `Route::resource()` inside the existing middleware/prefix/name group. Do NOT hand-write a per-verb `Route::get(...)` line.
    - Constrain the resource to the one action this skill builds, using `->only(['create'])`.
    - If the module's resource is already registered, add `'create'` to its existing `->only([...])` (or remove it from `->except([...])`) instead of registering it again.

```php
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('{module}', {ModuleName}Controller::class)->only(['create']);
});
```

    This yields `GET /admin/{module}/create` named `admin.{module}.create`, mapped to the Controller's `create` method.

2. **Controller Generation (`App\Http\Controllers\Admin\`):**
    - Generate a thin HTTP Controller named `{ModuleName}Controller.php` (verify the namespace against the existing controllers).
    - Declare `declare(strict_types=1);` and make the class `final`, extending the project's base `Controller`.
    - Include an empty constructor ready for future dependency injection. Leave a TODO naming what will be injected later: `{ModuleName}CommandService` and `{ModuleName}MapperContract` for the store use case, injected through the constructor with property promotion.
    - Create a single `create()` method that strictly returns the Blade view (e.g. `view('backend.pages.{module}.create')`).
    - Write DocBlocks in the project's plain style: short, human, 1-2 lines (e.g. "Shows the create form.").

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class {ModuleName}Controller extends Controller
{
    public function __construct()
    {
        // TODO: inject {ModuleName}CommandService and {ModuleName}MapperContract
        //       (constructor property promotion) when the store use case is built.
    }

    /**
     * Shows the create form.
     */
    public function create(): View
    {
        return view('backend.pages.{module}.create');
    }
}
```

3. **Blade Form View Generation (`resources/views/backend/pages/{module}/create.blade.php`):**
    - The path and view name above follow the project's existing backend views (the same directory as `index.blade.php` and `edit.blade.php`). Verify against the real views directory and use it. Do not create a parallel `admin/` tree.
    - Create a semantic, accessible HTML/Blade form structure: every input has a `<label for>` that matches its `id`, and required columns (NOT NULL without a default) get the `required` attribute.
    - STRICTLY set `enctype="multipart/form-data"` ONLY if the schema includes file/image columns (e.g., thumbnail, image, logo).
    - Always include the CSRF token with the `@csrf` directive.
    - Map columns 1:1 to appropriate HTML5 input types:

        | Column type                 | Control                                            |
        | --------------------------- | -------------------------------------------------- |
        | VARCHAR                     | `<input type="text">`                              |
        | TEXT                        | `<textarea>`                                       |
        | INT / DECIMAL               | `<input type="number">` (with `step` for decimals) |
        | TINYINT(1) / ENUM           | `<select>` with the schema's values                |
        | file/image column           | `<input type="file">` (with `enctype` above)       |

    - Inject Laravel `old()` helpers into the `value` attributes (and `selected` / textarea content) to preserve state upon validation failure.
    - Select options come from the schema's values as written. Do not call Domain enums, Services, or models from the view. Wiring options from the Domain enum comes later through a Query Service and ResponseDTO.

## Testing & Verification

- **Temporary Action:** For initial scaffolding, set the form `action` attribute to `#` with `method="POST"`. This lets the form render without a `RouteNotFoundException` before the store pipeline exists.
- **Visual Feedback:** Include a small visible alert or comment block inside the view reminding that the store route is not yet defined (e.g. `admin.{module}.store`), so it is easy to spot during early UI testing. Do not leave `#` without this reminder.
- **Tests:** Unit tests are paused (`AGENTS.md` - UNIT TESTING POLICY). Do not generate any.

## Verification Checklist (fill in only from what the generated code actually contains)

Before reporting done, audit the generated files. Claim only what you can point to in the code. A false claim is a defect.

- [ ] Only schema columns appear in the form; `id`, timestamps, `deleted_at`, and domain-generated fields (`slug`, `uuid`, `token`) are absent.
- [ ] Route uses `Route::resource()->only(['create'])` inside the existing group, with no hand-written per-verb line.
- [ ] Controller is `final`, has `declare(strict_types=1);`, an empty constructor with a TODO, and only a `create()` method that returns the view.
- [ ] No `store()`, FormRequest, Service, mapper, or Repository usage was added.
- [ ] Nothing under `core/` was created or changed.
- [ ] Namespace, view path, and view name match the existing codebase (verified, not guessed).
- [ ] Form uses `method="POST"`, `@csrf`, and `action="#"` with the visible "store route not defined yet" reminder.
- [ ] `enctype="multipart/form-data"` is present if and only if the schema has file/image columns.
- [ ] Every input has a matching `<label for>` / `id`, `old()` is used for every field, and `required` follows the schema.

## Output

Deliver the generated code components cleanly. You MUST wrap each code component inside standard markdown code blocks (using backticks with the language, such as `php` and `blade`) to strictly preserve line breaks, indentation, and formatting. Keep conversational text to an absolute minimum, and end with the filled-in verification checklist.
