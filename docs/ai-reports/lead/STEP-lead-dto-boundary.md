# Lead — Service Layer DTO Boundary

- **Date:** 2026-10-23
- **Scope:** Enforce the Response DTO mandate (AGENTS.md) on the two `LeadService`
  write methods that still returned a Domain Entity. Requested by the developer as
  a Clean Architecture enforcement pass.
- **Trigger:** Standing policy prompt, followed by an audit that found the
  violations, followed by explicit developer authorisation of the scope
  ("Fix both write methods") and the datetime handling ("Reuse mapper, emit
  ISO-8601").

## The defect

Two things were true at once, and only one of them is what the policy text named.

`AGENTS.md` — RESPONSE DTO MANDATE says a Service "MUST NOT return Entities, Eloquent
models, paginators, framework Request/Response objects". The policy prompt as
originally worded forbade "raw Eloquent Models, Collections, or generic arrays",
which would **not** have caught this: what actually leaked was a pure Domain
`LeadEntity`, a type not in that enumeration. The AGENTS.md clause is the one that
bites, and it is the clause this step enforces.

| Method | Before | After |
|---|---|---|
| `LeadService::getLeads()` | `PaginatedResult<LeadResponseDTO>` | unchanged (already compliant) |
| `LeadService::getLeadById()` | `LeadResponseDTO` | unchanged (already compliant) |
| `LeadService::createLead()` | `LeadEntity` | `LeadResponseDTO` |
| `LeadService::changeStatus()` | `LeadEntity` | `LeadResponseDTO` |

Two independent pieces of evidence that this was a real defect and not a style
preference:

1. `Api\V1\LeadController::store()` passed `$result->getData()` straight into the
   JSON body, so a raw domain entity was serialised onto the wire. The presentation
   adapter was doing the mapping the Application layer owed it — which is exactly
   the coupling the mandate exists to prevent.
2. `Api\V1\LeadController::updateStatus()` **discarded** the entity it had just been
   handed and issued a second full `getLeadById()` read purely to obtain a
   presentable shape. The controller's own comment recorded the reason: *"The status
   write returns a bare entity, so read the lead back."* Its docblock had drifted
   from its code — the comment said "bare entity", the docblock said "Array of
   LeadResponseDTO". A redundant query per status change was the ongoing cost.

`LeadConversionService::convert()` was audited and is **already compliant** — it maps
to `LeadConversionResponseDTO`. It was left untouched.

## What changed

Four files:

1. `core/Lead/Application/Mappers/LeadResponseMapper.php`
   - Added `toResponseDTOFromEntity(LeadEntity): LeadResponseDTO`.
   - Extracted the shared field mapping into a private `build()`, so the read entry
     point (`toResponseDTO(LeadProjectionDTO)`) and the new write entry point cannot
     drift apart.
   - Added a private `toAtom(string): ?string` that parses the entity's stored
     wall-clock string at UTC and formats ISO-8601.

2. `core/Lead/Application/Services/LeadService.php`
   - `createLead()` now returns `toResponseDTOFromEntity($leadEntity)`.
   - `changeStatus()` now returns `toResponseDTOFromEntity($lead)`.
   - Docblocks corrected from "Contains the LeadEntity" to "Contains the
     LeadResponseDTO".

3. `core/Lead/Application/Contracts/LeadServiceInterface.php`
   - Both `@return` docblocks corrected. No signature change — `Result` was already
     the declared type, so no consumer's type declarations were affected.

4. `app/Http/Controllers/Api/V1/LeadController.php`
   - Removed the redundant second read in `updateStatus()`. It now uses the data the
     Service already returned.

## Why `toResponseDTOFromEntity` and not a projection

`LeadResponseMapper` took only a `LeadProjectionDTO`, while every sibling module's
response mapper (`UserResponseMapper`, `CompanyResponseMapper`, `DealResponseMapper`)
takes an **Entity**. That asymmetry is real but it is not arbitrary: `LeadEntity`
carries `createdAt`/`updatedAt` as raw storage strings, and the read path deliberately
parses them into `DateTimeImmutable` in `LeadRepository`/`LeadEntityMapper` before
mapping.

Keeping the projection entry point intact and adding a second entry point on the same
mapper was chosen over three alternatives:

- **Widening `toResponseDTO()` to a union of projection and entity** — makes the
  mapper's contract ambiguous and would have rippled into the query path.
- **Passing raw strings through from the entity** — would have shipped
  `"2026-10-05 10:13:16"` on `POST /leads` while `GET /leads/{id}` said
  `"2026-10-05T10:13:16+00:00"`. Two wire formats for one field; rejected.
- **Re-reading after the write inside the Service** — an extra query, and it would put
  a read inside a Command Service.

No repository contract was widened. `saveStatus()` was deliberately left as
`void` rather than changed to return the saved `LeadEntity`, because that would have
been a cross-module contract change beyond the authorised scope and would have broken
the paused `LeadConversionServiceTest` mock. See D-022.

## Verification

Every claim below was checked against the files on disk, not from memory.

- `php -l` clean on all four touched files.
- Grep of `LeadService.php` for `Result::success` — exactly 4 sites, all four payloads
  confirmed by reading the lines: `PaginatedResult`, `LeadResponseDTO`,
  `LeadResponseDTO`, `LeadResponseDTO`. Zero entity payloads remain.
- Grep confirmed no `LeadEntity` reference survives in `LeadService.php` outside the
  `LeadData` construction at line 135 (an input object, not an output).
- Live check via `php artisan tinker` — `toResponseDTOFromEntity()` emits
  `2026-10-05T10:13:16+00:00`.
- Live read-path vs write-path comparison — both entry points produce **identical**
  JSON for the same lead and timestamp (`IDENTICAL: YES`).
- Controller audit: no `LeadEntity` import in either controller; all four
  `getData()` sites derive from Service-produced DTOs.

## Not done / open

- **No tests were run or written.** Testing is paused by the AGENTS.md UNIT TESTING
  POLICY, and the prompt did not ask for an override.
- **`LeadConversionServiceTest` and `LeadStatusChangeTest` remain stale.** They
  construct `LeadEntity` through PHP 8 named arguments, and earlier committed work
  (`1cceff9 refactor`) changed `LeadEntity`'s constructor signature — its `status`
  property is now `private` with a `getStatus()` accessor. These were already failing
  before this step and this step neither fixed nor worsened them. The prompt did not
  ask for tests to be touched.
- **`updateStatus()` still returns stored, pre-write `updated_at`.** Making it return
  the true post-write value requires `saveStatus()` to return the saved entity, which
  was ruled out of scope. Commented in place so it is not mistaken for fresh data
  later; see D-022.
- **`LeadConversionService` maps to its own `LeadConversionResponseDTO`.** Not touched;
  whether that should converge on `LeadResponseDTO` is an open question for the
  developer, not a defect.

## Decisions recorded

- **D-022** — Write-path `updated_at` is the pre-write value; `saveStatus()` stays
  `void` rather than widening a cross-module repository contract.

See `docs/ai-reports/DECISIONS.md`.
