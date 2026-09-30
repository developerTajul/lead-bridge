---
name: update-action-builder
description: Trace and review the end-to-end update flow in Hexagonal Architecture. Enforces strict Result Pattern usage in Controllers, transaction rollbacks, and file cleanup.
---

# Update Action Builder Skill

## Description

Trace and review what happens end-to-end when a user clicks "Update" on an Update Form in a Laravel Clean/Hexagonal Architecture project — from the browser request through Middleware, Route, FormRequest, Controller, Mapper, Service (transaction + business logic), Repository, Model/DB, and back to the response. Use this skill whenever the developer asks to build, review, or debug an update flow for any module (Category, Product, Auth, etc.), whenever reviewing a Controller/Service/Repository trio for an update action, or whenever checking for the known bug classes (missing rollback on early return, TOCTOU race conditions, orphaned uploaded files, Result pattern misuse, LSP violations). Also use when asked to trace or explain "what happens when the update button is clicked."


# Laravel Update Flow — Lifecycle & Review Skill

This skill traces **what happens after the "Update" button is clicked**, step by step, through the full request lifecycle in a Clean/Hexagonal Architecture project — and reviews each layer against the bug classes that have been found repeatedly in real codebases.

Use this skill for two purposes:
1. **Explaining/tracing** the full lifecycle when asked (e.g. "what happens when the update button is clicked")
2. **Reviewing** a new or existing Controller → Service → Repository trio against the Known Bug Watchlist below

## Quick Reference: Full Lifecycle (top to bottom)

```
Browser (Update button click, form submit)
   │
   ▼
HTTP Request (PUT/PATCH) ──► Route (web.php/api.php)
   │
   ▼
Middleware stack (auth, CSRF, throttle, etc.)
   │
   ▼
FormRequest (e.g. CategoryUpdateRequest)
   — authorize() + validated rules run BEFORE the controller method executes
   │
   ▼
Controller::update()
   — thin, orchestration only
   — Mapper: array → DTO (request → CategoryUpdateDTO)
   — calls Service, translates Result → HTTP response
   │
   ▼
Service::updateCategory()  ← business logic lives here
   1. beginTransaction()
   2. findById()          — existing entity fetch (read)
   3. business rules      — slug regen if name changed, thumbnail upload, etc.
   4. repository->update()— actual persistence (write)
   5. commit() / rollback()
   6. side-effect cleanup — delete old/orphaned files AFTER commit
   │
   ▼
Repository::update() / findById()
   — Model-facing, translates array/DTO ⇄ Domain Entity via mapper
   │
   ▼
BaseRepository (findRecord/updateRecord)
   — raw Eloquent: find(), fill(), isDirty(), save()
   │
   ▼
Database
   │
   ▼
Result (success/failure) bubbles back up through Repository → Service → Controller
   │
   ▼
Controller returns RedirectResponse (back()->with('error') or redirect()->with('success'))
   │
   ▼
Browser re-renders (session flash message shown)
```

## Responsibilities & Known Bug Watchlist Per Layer

Apply the checklist below when reviewing each layer. These are all drawn from bugs actually found in real projects.

### 1. FormRequest
- [ ] Validation rules cover format only, not business invariants (e.g. slug uniqueness) — business rules belong in the Service layer, not the FormRequest.
- [ ] `authorize()` is correctly handled (no missing policy/gate check).

### 2. Controller
- [ ] **Stays thin** — no business logic, no direct Model/Repository access. Only Mapper → Service → Result-to-response.
- [ ] `Result::isFailure()` is checked and both branches (success/failure) are handled.
- [ ] **NEVER uses try-catch blocks to catch Domain Exceptions** — the Service layer MUST catch its own exceptions internally and return a `Result::failure` object. The Controller MUST remain thin and ONLY evaluate the returned `Result` object (e.g., `if ($result->isFailure())`).
- [ ] LSP check: this Controller doesn't violate the contract of any abstract base class/interface it implements (a previously found issue class).

### 3. Service (this is where most bugs have been found — the most important section)

- [ ] **No leftover debug statements** — `dd()`, `dump()`, `var_dump()` in any line.
- [ ] **Every early-return inside a transaction MUST return a `Result::failure`** — the Service layer MUST catch its own exceptions internally and return a `Result::failure` object. The Controller MUST remain thin and only evaluate the returned `Result` object.
- [ ] **TOCTOU race condition — MANDATORY row-level locking.** Inside an update transaction, the initial read of the target record MUST use a locking read, never a plain read. Concretely:
  - The Service MUST call `$repository->findByIdForUpdate($id)`, NOT `findById($id)`, for the "fetch existing entity" step of `update*()`.
  - `findById()` inside a transaction is a **🔴 Critical violation** — it takes no lock, so a concurrent request can mutate the row between the read and the write. A `UNIQUE` index only protects the slug; every other column (price, status, featured_image) is left racy.
  - The module's `RepositoryContract` MUST declare `findByIdForUpdate(int $id): ?{Module}Entity`, and the concrete Repository MUST implement it via `BaseRepository::findRecordForUpdate()` (which applies `lockForUpdate()`). Do not re-implement the lock in the module repository.
  - The post-write refresh read (reloading the entity so it contains newly-created child rows) MAY use plain `findById()` — the row is already locked by that point in the transaction.
  - Unit tests MUST stub `findByIdForUpdate` for the pre-write read; a test that only stubs `findById` will silently pass against a non-locking implementation and is itself a violation.
- [ ] **File cleanup is correct on every branch**:
  - If a new thumbnail was uploaded and a later step (e.g. repository update) fails, is the uploaded file deleted (orphan file bug)?
  - On the success path, is the old thumbnail deleted **after** commit (deleting before commit risks losing the old file if a rollback happens)?
- [ ] **Result Pattern used correctly** — `Result` is appropriate when there are two valid business outcomes (success/not-found); a direct return type is appropriate when data is always guaranteed (avoid over-engineering).
- [ ] **Duplicate failure messages/error codes** are DRY'd up (via a helper method).
- [ ] Service layer stays output-agnostic — behaves the same whether called from Web, API, or CLI.

### 4. Repository
- [ ] Repository only handles persistence + Entity mapping — no business rule (slug logic, thumbnail logic) has leaked in here.
- [ ] `findById()`/`update()` return null-safely rather than throwing (the Service layer decides based on null, not the Repository).
- [ ] If a shared type like `PaginatedResult<T>` is involved, it lives in `Shared\Domain` (to avoid Dependency Rule violations) — check this too when reviewing listing/index endpoints.

### 5. BaseRepository
- [ ] `isDirty()` is checked to avoid unnecessary `save()` calls (correct here — keep this pattern in future modules too).
- [ ] Mass-assignment (`fill()`) has `$fillable`/`$guarded` properly set — otherwise unintended fields can be overwritten.

### 6. View

- [ ] **View Update Guard:** The edit.blade.php file MUST be updated to replace the `action="#"` placeholder with the actual PUT/PATCH route once the update route is wired.

## Review Workflow

1. Read files in this order: Controller → Service → Repository → BaseRepository.
2. Check each layer against its checklist above, paying special attention to **the Service layer's transaction/rollback and file-cleanup sections** — this is where most bugs are found.
3. Report findings by severity: 🔴 Critical (data integrity/transaction bug), 🟡 Design concern (TOCTOU, DRY, over-engineering).
4. If the same bug class could plausibly occur in another module (Auth, Product, etc.), flag it and suggest adding it to **Agents.md's Known Bug Watchlist**.

## Notes

- This skill is project-specific — written assuming a Clean/Hexagonal Architecture (Domain/Application/Infrastructure layers, Result Pattern, `PaginatedResult` in `Shared\Domain`).
- When a new bug class is found, add it to the Known Bug Watchlist section above and update this skill, so future reviews don't miss it again.