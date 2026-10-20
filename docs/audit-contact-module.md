# Contact Module — Architecture Audit

**Module:** `Core\Contact`
**Branch:** `contacts`
**Audited against:** `AGENTS.md`, `.claude/rules/04-crud-pipeline.txt`, `.claude/rules/05-rich-domain-and-services.txt`, `.claude/rules/08-events-and-queues.txt`
**Status:** Fixes applied — all findings below are closed except where marked OPEN.
**Verification:** `php -l` clean on all 10 modified files. Laravel 13.34.0 / PHP 8.3.30 boots.

---

## 1. Scope

| Layer | Artifacts audited |
|---|---|
| Domain | `ContactEntity`, `ContactRules`, `CompanyLink`, `ContactFilter`, `StoredContactData`, `ContactDetailData`, `InvalidContactException`, `ContactRepositoryContract`, `ContactQueryContract` |
| Application | `ContactService`, `ContactQueryService`, `ContactMapper`, `ContactResponseMapper`, `ContactMapperContract`, 6 DTOs |
| Infrastructure | `ContactEloquentRepository`, `ContactEntityMapper` |
| Delivery | `Api\V1\ContactController`, `Web\Backend\ContactController`, 6 FormRequests, `ContactServiceProvider`, routes, 4 Blade views |

Unit testing is paused per `AGENTS.md` (UNIT TESTING POLICY). No tests were written, and no test claims are made in this report. Verification is limited to syntax linting and static reading of the generated code.

---

## 2. Verdict

The module follows the prescribed architecture well. Core is clean: **zero `Illuminate\*` imports exist in any Domain or Application file**. Command/Query separation is respected, repositories traffic in Entities and DataObjects, and every Service method returns `Result` wrapping a ResponseDTO. Business rules live in the Entity and the `CompanyLink` Value Object.

The defects found were concentrated in one unreachable feature (company linkage on update), two broken docblock tags, and provider boilerplate.

---

## 3. Findings and Resolution

### A1 — Company linkage silently ignored on update — **FIXED**

**Severity: High.** `UpdateContactDTO` carried no company fields, `ContactMapper::mapToUpdateDTO()` never read `company_id`, and `ContactEloquentRepository::update()` wrote only the scalar columns. A caller could create a contact linked to a company but could never change or remove that link. The update path was a silent no-op for linkage.

**Root cause of the delay:** the DTO needs to express three distinct intents — leave the link alone, attach to a company, detach — and `?int $companyId` plus a single `bool` flag cannot represent "attach to company #2". Every naive encoding silently changed one intent into another. This required a semantics decision, not a mechanical patch.

**Resolution — explicit attach + detach flags:**

- `UpdateContactDTO` gained `?CompanyLink $companyLink` (the link to attach) and `bool $clearCompany` (detach). A null link with a false flag means "leave the stored link alone", so a partial update that never mentions the company cannot unlink the contact.
- A `withCompanyLink()` clone was added, since deriving the link requires the Domain Value Object. This follows the `with*()` clone convention from rule 04 Step 1 and keeps the DTO free of factory logic.
- `ContactMapper::mapToUpdateDTO()` now branches on `array_key_exists('company_id')`: absent leaves the link alone, blank detaches, otherwise it builds a `CompanyLink` through the Value Object's own guard.
- `ContactEntity::applyCompanyChange()` was added as the entity behaviour method. It refuses a payload that both attaches and detaches, because that is a contradiction rather than something to resolve silently.
- `ContactRepositoryContract::update()` and `ContactEloquentRepository::update()` gained `?CompanyLink $link` and `bool $clearCompany`.
- Delivery wiring: both `UpdateContactRequest` classes validate the new fields, and `edit.blade.php` renders company, job title, and primary inputs prefilled from the contact's current link.

**Adapter detail:** the pivot is unique on `(company_id, contact_id)`, so re-attaching an existing company would collide. The adapter uses `syncWithoutDetaching()`, which writes the same row and refreshes the role on it. The read-back was also moved *inside* the transaction (`$record->refresh()`), because the previous implementation returned a row read before the pivot write, so the response could not have shown the link it had just written.

### A2 — `$clearPhone` was dead when a phone value was present — **FIXED**

**Severity: Medium.** `applyChanges()` resolved the phone with `$clearPhone ? null : ($phone ?? $this->phone)`. Because the flag was tested *first*, a payload of `clearPhone: true` with `phone: '555'` stored `'555'` and ignored the flag entirely. The fix is a guard clause: the value fallback runs first, then the flag clears it. This also matches the coding standard's preference for guard clauses over ternaries.

**Note on method casing:** the service called `applyChanges()` while the entity declares `applyChanges()`. PHP method names are case-insensitive, so the call resolved correctly and was **not** a defect. It was audited and left unchanged.

### A3 — Broken `@implements` docblock tags — **FIXED**

**Severity: Low (docblock compliance).** `ContactEloquentRepository` carried `@implements \Core\Contact\Domain\Contracts\...`. The leading backslash makes the tag an unresolvable FQCN and violates the STRICT DOCBLOCK MANAGEMENT mandate. Both tags were rewritten as bare interface names, matching the working convention already used in `ContactMapper`.

### A4 — Provider boilerplate — **FIXED**

**Severity: Low.** `App\Providers\ContactServiceProvider` lacked `declare(strict_types=1);` and used `class` rather than `final class`.

**Verification:** the provider **is** registered correctly in `bootstrap/providers.php` (line 14). Rule 04 Step 4/5 mentions the composition root generally; the project's established convention is one provider per module, and `ActivityServiceProvider`, `CompanyServiceProvider`, and `LeadServiceProvider` all follow it. No change was needed to the binding location.

### A5 — Email uniqueness enforced only in the FormRequest — **OPEN**

**Severity: Low.** Both `StoreContactRequest` classes apply `unique:contacts,email`, but `ContactEntity` declares no uniqueness invariant. Rule 05 R9 lists "business rule duplicated in a Controller, FormRequest, or Repository" as an anti-pattern, and the `contacts.email` column is in fact `unique`.

This is genuinely two-sided: shape validation is the FormRequest's job, and a uniqueness check needs the database, which the Entity cannot reach. Closing it properly means deciding whether uniqueness is a real domain invariant (enforced by catching a storage conflict inside the Service and converting it to `Result::failure`) or purely input validation. **Left open deliberately** — it needs a product decision, and changing it touches the store path, which was not in scope.

---

## 4. Deliberately Unchanged

**No domain events.** `ContactEntity` has no `domainEvents` list and `releaseEvents()`, so it predates rule 08. Its `applyChanges()` and `applyCompanyChange()` cannot pass events forward. Because rule 08 is scope-optional per module ("YAGNI applies to business events") and the task did not ask for events, no event machinery was invented. **Trade-off recorded:** until events are added, the module is not rule-08 compliant, and the event-ordering rules (release before persist, dispatch after commit) have no bearing on it.

**`applyChanges()` signature.** Left untouched to avoid an unrequested API change. The company change travels through its own behaviour method, which is the correct shape anyway: the company link is not part of the entity's own state — it lives on the pivot table — so passing a `CompanyLink` into `applyChanges()` would have implied a state field that does not exist.

**Multi-company editing.** A contact can in principle hold several company rows, but the domain models only `is_primary` as a role and no current UI creates more than one. The edit form therefore prefills from `$contact->companies[0] ?? null` and the update writes exactly one link. Supporting many links needs its own use case and was out of scope.

---

## 5. Confirmed Compliant

- No `Illuminate\*`, WordPress, or framework import anywhere in Core Domain or Application.
- `ContactService` is Command-only; `ContactQueryService` is Query-only. The Query Service opens no transaction, dispatches no events, and performs no writes.
- Repositories take and return `ContactEntity` / `StoredContactData` / `ContactDetailData` — never DTOs, loose arrays, or ORM objects.
- Entity is `readonly` and immutable; behaviour methods return new instances; `create()` and `reconstitute()` named constructors; invariants enforced in the Entity.
- `CompanyLink` guards the pivot values (positive company ID, job-title length).
- `ContactMapperContract` accepts a plain `array`, never a `FormRequest` — Core stays framework-agnostic.
- ResponseDTOs are `final readonly` with plain PHP types and are `Result`-wrapped.
- Guard clauses used instead of ternaries in all controllers.
- Locking read `findByIdWithLock()` runs through `BaseRepository::findRecordForUpdate()`.
- `ContactServiceProvider` binds each port to its adapter once.

---

## 6. Files Changed

| File | Change |
|---|---|
| `core/Contact/Application/DTOs/Requests/UpdateContactDTO.php` | Added `companyLink`, `clearCompany`, `withCompanyLink()` |
| `core/Contact/Application/Mappers/ContactMapper.php` | Company-link branch, explicit clear flags, `isSubmittedAndBlank()` |
| `core/Contact/Application/Services/ContactService.php` | Passes the company change; calls `applyCompanyChange()` |
| `core/Contact/Domain/Entities/ContactEntity.php` | Added `applyCompanyChange()`; `$clearPhone` guard-clause fix |
| `core/Contact/Domain/Exceptions/InvalidContactException.php` | Added `contradictoryCompanyChange()` |
| `core/Contact/Domain/Contracts/ContactRepositoryContract.php` | `update()` signature extended |
| `core/Contact/Infrastructure/Repositories/ContactEloquentRepository.php` | Pivot write, transactional read-back, `@implements` fix |
| `app/Http/Requests/Api/V1/UpdateContactRequest.php` | Company-linkage validation |
| `app/Http/Requests/Web/Contact/UpdateContactRequest.php` | Company-linkage validation |
| `app/Providers/ContactServiceProvider.php` | `declare(strict_types=1)`, `final class` |
| `resources/views/admin/contacts/edit.blade.php` | Company, job title, primary inputs |

---

## 7. Verification Performed

- `php -l` on all 10 modified PHP files — no syntax errors.
- `php artisan about` — framework boots; Laravel 13.34.0, PHP 8.3.30.
- Contract/adapter `update()` signatures compared directly: identical parameter types, names, and order, with the adapter adding the `: StoredContactData` return type the contract declares. This is a valid implementation.

**Not verified — stated plainly:** no runtime or behavioural verification was performed. No test suite was run (testing is paused), and no HTTP request was executed against the update endpoint. The company-linkage path has been reviewed statically and lints clean, but it has not been exercised against a live database.

---

## 8. Open Follow-ups

1. **A5** — decide whether email uniqueness is a domain invariant enforced in the Service, or purely FormRequest validation.
2. **Domain events** — add a `domainEvents` list and `releaseEvents()` to `ContactEntity` if the module is to raise events (e.g. `ContactCreatedEvent`). Requires the rule-08 write-flow ordering.
3. **Multi-company links** — add a dedicated use case if contacts must belong to several companies with per-link roles.
