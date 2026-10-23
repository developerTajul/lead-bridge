# Step Report 05 - Deal Web Views and Navigation

- **STEP ID:** 05
- **MODULE:** Deal
- **DATE:** 2026-10-20
- **STATUS:** COMPLETE
- **BRANCH:** main

## Goal

Build the four Deal Blade pages, the shared form partial, the stage picker's
pipeline narrowing, and the sidebar entries, so the web delivery adapter written
in Step 04 has something to render.

## Files Created

| File | Purpose |
| --- | --- |
| `resources/views/admin/deals/index.blade.php` | Paginated list, pipeline filter, row actions. |
| `resources/views/admin/deals/create.blade.php` | Create form. |
| `resources/views/admin/deals/edit.blade.php` | Edit form, prefilled from the detail read. |
| `resources/views/admin/deals/show.blade.php` | One deal, its links and its activities. |
| `resources/views/admin/deals/partials/form.blade.php` | The fields create and edit share. |

## Files Modified

- `resources/views/layouts/master.blade.php` — added `@stack('scripts')` after the
  bundled scripts, so a page script runs once jQuery and the other libraries are
  loaded.
- `app/Http/Controllers/Web/Backend/DealController.php` — `index()` now merges the
  form options into its view data, so the list can resolve the pipeline filter and
  the linked-record labels.
- `resources/views/layouts/partials/asidebar.blade.php` — Deals branch.
- `resources/views/layouts/partials/asidebar-small.blade.php` — Deals branch.
- `resources/views/layouts/partials/asidebar-mobile.blade.php` — Deals branch.

### Core files changed during this step

Verification of the write path turned up two real defects in the update chain,
both in code this module owns. They are recorded here rather than as scope
violations because the step cannot be reported working without them.

- `core/Deal/Domain/Entities/DealEntity.php` — `applyChanges()` gained
  `$unlinkCompany` / `$unlinkContact`.
- `core/Deal/Application/DTOs/Requests/UpdateDealDTO.php` — carries the two flags.
- `core/Deal/Application/Mappers/DealMapper.php` — added
  `toSubmittedOptionalId()`, `isUnlinking()`, `toPositiveId()` and
  `requireSubmitted()`.
- `core/Deal/Application/Services/DealService.php` — passes the flags through.

## Architecture Notes

### Unlinking cannot be expressed with null, so the DTO carries a flag

`UpdateDealDTO` documents "absent = leave the stored value alone". The web form
posts *every* field it shows, including a company and contact picker, so an
emptied picker arrives as `''` — which the web `UpdateDealRequest` deliberately
accepts as "unlink". The shared mapper treated that blank as a client error, so
**every web update was silently rejected** and the redirect carried an error
flash (see the defect note below).

`null` already means "leave it alone", so no null can mean "clear it". The DTO
therefore carries `unlinkCompany` / `unlinkContact` booleans, set by the mapper
from "the key was present and blank", and the entity applies them.

The API is unaffected: a client that omits the key sends no flag, and the entity
still keeps the stored link. A blanked *required* field — title, amount,
currency, pipeline, stage — is still refused everywhere.

### The list shows names, not ids

The paginated read returns flat `DealResponseDTO`s carrying only foreign-key ids,
because hydrating four relations for every row is exactly what the read side is
meant to avoid. The list therefore resolves its labels from the same form-options
read the form uses. `index()` merges that into the view data.

Trade-off recorded as D-009: one extra options read per list page. A joined read
model on `DealQueryContract` would be better at scale, but that is a schema-level
decision, not one to invent here.

### The stage picker narrows to the chosen pipeline

A stage belongs to one pipeline, so offering every stage from every pipeline would
let a user submit a stage that cannot occur under the chosen pipeline. The partial
tags each stage option with `data-pipeline-id` and a small script filters the list
on pipeline change, clearing the selection if it no longer belongs.

The script sits in `@once` + `@push('scripts')`, so including the partial once per
page emits it once. `master.blade.php` gained the matching `@stack('scripts')`.

### A closing stage is called out, not hidden

`actual_close_date` is derived (Step 03), so neither form shows it as an input.
The edit form explains that moving off a closing stage clears it; the show page
labels a null close date "Still open" and a stored one as a badge.

## Verification (all evidence from actual execution)

### 1. Syntax
`php -l` on every file changed in this step: **no syntax errors detected**.

### 2. Pages render through the Laravel HTTP kernel (real DB, authenticated)
```
/admin/deals          200  bytes=166159  error_markup=no
/admin/deals/create   200  bytes=148050  error_markup=no
/admin/deals/1        200  bytes=146626  error_markup=no
/admin/deals/1/edit   200  bytes=148739  error_markup=no
```

### 3. List content (real seeded rows)
```
Selion title: yes        Rahman title: yes      Renewal title: yes     Expansion title: yes
amount 250000.00 BDT: yes        amount 1000.50 BDT: yes
closed badge 2026-10-13: yes
company label: yes       stage label Closed Lost: yes     stage label Closed Won: yes
deal count pill: yes     filters form present: yes       pipeline options rendered: yes
create link present: yes
```

### 4. Detail page content
```
shows amount: yes        shows stage name: yes      link to companies: yes
```

### 5. Create form content
```
pipeline options: yes                       stage options carry pipeline id: yes
company options: yes                        contact options: yes
stage-narrowing script: yes
```

### 6. Edit form content
```
prefilled pipeline selected: yes            update action present: yes
```

### 7. Hostile and empty inputs
```
/admin/deals?pipeline_id=999  -> 200  (empty state shown)
/admin/deals?per_page=abc     -> 200  (warning flashed, list still renders)
/admin/deals/999999           -> 302  (redirect, no error page)
```

### 8. Write path through the controller (real DB, scratch row)
```
store   -> new row, amount=4321.50 BDT, company=1 contact=1
          expected_close_date=2026-12-01
          actual_close_date on an open stage: (null)
update  -> title=Scratch Deal Renamed amount=5000.00
          contact blanked in the form -> stored as: NULL
          actual_close_date after a closing stage: 2026-10-20
update  -> actual_close_date after reopening: (null)
update  -> blank title rejected: title
destroy -> row gone: yes
max_id=4 expected=4 -> unchanged, ids [1,2,3,4]
```

### 9. Mapper rules (direct, real container)
```
API update, absent company   -> companyId=NULL unlinkCompany=false
Web form, blank picker       -> companyId=NULL unlinkCompany=true
Supplied value               -> companyId=3 unlinkCompany=false
Blanked required field       -> title: rejected, amount: rejected, currency: rejected,
                                pipeline_id: rejected, deal_stage_id: rejected
Entity, unlink               -> company=NULL contact=NULL (original untouched)
Entity, title-only change    -> company=2 contact=1 (links kept)
```

### 10. Deal API regression (the shared mapper changed)
```
POST   /api/v1/deals                  -> 201 id=12 title=API Scratch Deal
GET    /api/v1/deals                  -> 200 count=5 total=5
GET    /api/v1/deals?per_page=999     -> 422
GET    /api/v1/deals/12               -> 200 stage=Negotiation company=... contact=...
PUT    absent key                     -> 200 company kept: 1  contact kept: 1
PUT    closing stage                  -> 200 actual_close_date=2026-10-20
PUT    blank title                    -> 422
PUT    actual_close_date supplied     -> 422
GET    missing deal                   -> 404
DELETE                                -> 200 row gone: yes
GET    no token                       -> 401
```

### 11. Portability audit
`grep -rn "Illuminate|use App\|wp_|do_action|WordPress"` over `core/Deal/Domain` and
`core/Deal/Application` — **(none)**.

## Defects found and fixed during verification

Both were found by running the write path, not by reading it.

1. **Every web update was rejected.** The mapper refused a blank `contact_id`, but
   the web form posts an empty picker whenever the user does not choose one. The
   redirect carried `Field [contact_id] cannot be cleared, it needs a value.` and
   nothing was saved. Fixed with the unlink flags described above.

2. **A blanked `amount` or `currency` was silently accepted on an update.** Both
   helpers treated a blank as "leave alone", contradicting the rule that these are
   required fields, while the form request validated them as `required`. A client
   submitting an empty amount got a 302 and no change. Now rejected, matching the
   other three required fields.

A third issue was mine, not the code's: the first draft of `index.blade.php` read
`$deal->company`, `$deal->stage` and `$pipelineOptions` without either the
relation existing on the list DTO or the variable being defined. The page threw
a 500 until both were corrected. It is noted because the anti-hallucination
protocol requires that a claim only be made for code that was actually run.

## Data Cleanup

Every scratch row created during verification was deleted. `deals` holds ids
`[1,2,3,4]` with `max_id=4`; `Scratch%` rows: 0. All scratch Sanctum tokens were
revoked — `personal_access_tokens` holds only the 10 pre-existing `auth-token`
rows. The four verification scripts were removed; `verify_web_pipeline.php`
(pre-existing) was left alone. No pre-existing row was modified.

## Definition of Done

- [x] All four pages render, with real data, through the HTTP kernel.
- [x] Views hold no business logic — they render ResponseDTOs and route names.
- [x] The form partial is shared by create and edit rather than duplicated.
- [x] Stage options are narrowed to the chosen pipeline.
- [x] The derived close date is never an editable field.
- [x] Hostile query strings render a page instead of an error.
- [x] Sets `@yield`/`@push` correctly; the layout gained `@stack('scripts')`.
- [x] Sidebar entries added to all three navigation partials.
- [x] No framework import entered Domain or Application.
- [x] API regression checked, since the shared mapper changed.
- [ ] Tests — **not applicable**, UNIT TESTING POLICY is active.

## Known Limitations

- **The list page performs an extra form-options read** to resolve labels. See
  D-009. Fine at this size; a joined read model is the scale-up path.
- **`store()` still accepts `actual_close_date`** from API clients
  (`StoreDealRequest` declares it `nullable|date`). Carried forward from Step 03's
  known limitations; the web create form does not offer the field at all.
- The three sidebar partials duplicate the Deals branch. That is how every other
  module's navigation is written in this project, so it matches the surrounding
  code rather than introducing a partial-include the other branches do not use.
