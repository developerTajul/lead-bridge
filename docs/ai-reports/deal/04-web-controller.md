# Step Report 04 - Deal Web Controller

- **STEP ID:** 04
- **MODULE:** Deal
- **DATE:** 2026-10-20
- **STATUS:** COMPLETE
- **BRANCH:** main

## Goal

Give the Deal module its web delivery adapter: a thin Blade controller for the
list, create, show, edit, update and delete pages, the form requests behind it,
the container bindings and the resource routes.

## Files Created

### Framework / Delivery
| File | Purpose |
| --- | --- |
| `app/Http/Controllers/Web/Backend/DealController.php` | The seven resource actions. |
| `app/Http/Requests/Web/Deal/IndexDealRequest.php` | Query-string validation for the list. |
| `app/Http/Requests/Web/Deal/StoreDealRequest.php` | Create form validation. |
| `app/Http/Requests/Web/Deal/UpdateDealRequest.php` | Edit form validation. |

## Files Modified

- `app/Providers/DealServiceProvider.php` — binds `DealFormOptionsContract` to
  `DealFormOptionsRepository` (the only binding this step adds; the rest were
  bound in Steps 01-03).
- `routes/web.php` — `Route::resource('deals', DealController::class)` inside the
  existing `admin` / `admin.` group, after `contacts`.

## Architecture Notes

### The controller is thin, on purpose

Every action follows the same four beats: build the input DTO (through
`DealMapperContract`), call one service method, branch on `Result`, hand the
ResponseDTO to a view or a redirect. There is no business conditional in the
controller — the only `if`s are `isFailure()` guards and the existing
`errorStatus()` translation, written as early returns rather than ternaries.

### The list read is tolerant of a bad filter

`index()` is the one place a client can hand over arbitrary input without a form:
bookmarks, hand-edited URLs, older links. `IndexDealRequest` validates the query
string, but the mapper is stricter than the validator, so `buildFilterDTO()`
catches the mapper's `InvalidArgumentException`, falls back to an unfiltered
list at the default page size, and flashes a warning. A stale bookmark shows the
list rather than a 422 — the same behaviour the Contact and Pipeline list pages
already have. The accepted `per_page` is also clamped to `DealRules::MAX_PER_PAGE`
before the mapper sees it.

### Read options sit behind their own port

The list filter and the create/edit form both need rows from *other* modules'
tables: pipelines, stages, companies and contacts. Reaching for those Eloquent
models from the controller would put four models in the delivery layer and make
the controller untestable without a database. `DealFormOptionsContract` already
exists from Step 02, so the controller consumes `DealQueryService::getFormOptions()`
like any other read.

A failure there does not fail the page: `formOptions()` returns `[]`, so the form
renders with empty pickers and the save reports what is missing. A form that
cannot load its dropdowns is still a page you can look at.

### The stage's name is read before the mapper runs

`update()` calls `stageNameFor()` first. Whether the new stage closes the deal is
stated in names (`DealRules::WON_STAGE_NAME` / `LOST_STAGE_NAME`, see Step 03),
and only the repository knows the names, so the controller reads the name and
passes it alongside the id. That keeps the close-date rule in the entity, where
`moveToStage()` owns it, instead of leaking a Won/Lost comparison into delivery.

## Verification (all evidence from actual execution)

### 1. Syntax
`php -l` on the controller and all three web form requests: **no syntax errors
detected**.

### 2. Routes
`php artisan route:list --path=admin/deals`:
```
GET|HEAD  admin/deals            admin.deals.index   Web\Backend\DealController@index
POST      admin/deals            admin.deals.store   Web\Backend\DealController@store
GET|HEAD  admin/deals/create     admin.deals.create  Web\Backend\DealController@create
GET|HEAD  admin/deals/{deal}     admin.deals.show    Web\Backend\DealController@show
PUT|PATCH admin/deals/{deal}     admin.deals.update  Web\Backend\DealController@update
DELETE    admin/deals/{deal}     admin.deals.destroy Web\Backend\DealController@destroy
GET|HEAD  admin/deals/{deal}/edit admin.deals.edit   Web\Backend\DealController@edit
Showing [7] routes
```

### 3. Container resolution
```
App\Http\Controllers\Web\Backend\DealController
  -> App\Http\Controllers\Web\Backend\DealController
Core\Deal\Domain\Contracts\DealFormOptionsContract
  -> Core\Deal\Infrastructure\Repositories\DealFormOptionsRepository
Core\Deal\Infrastructure\Repositories\DealFormOptionsRepository
  -> Core\Deal\Infrastructure\Repositories\DealFormOptionsRepository
```

### 4. Form options read (direct, real DB)
```
getFormOptions() success: true
pipelines=4 stages=14 companies=6 contacts=4
```

### 5. Write path through the controller (real DB, scratch row)
```
store   -> redirect to /admin/deals
          title=Scratch Deal For Web Check amount=4321.50 currency=BDT
          company=1 contact=1 expected_close_date=2026-12-01
          actual_close_date on an open stage: (null)
update  -> title=Scratch Deal Renamed amount=5000.00
          contact blanked in the form -> stored as: NULL
destroy -> redirect to /admin/deals, row gone: yes
max_id=4 expected=4 -> unchanged
```

## Data Cleanup

The scratch deals created during verification were deleted. `deals` holds ids
`[1,2,3,4]` with `max_id=4`; no row titled `Scratch%` remains. The verification
scripts were removed. No pre-existing row was modified.

## Definition of Done

- [x] Strict typing on; namespaces match paths.
- [x] Controller holds no business logic — input -> mapper -> service -> view.
- [x] Both a Command Service and a Query Service are used, never mixed.
- [x] Every action consumes a `Result` and returns a ResponseDTO to the view.
- [x] Reads from other modules behind `DealFormOptionsContract`.
- [x] No direct Eloquent or `DB::` call in the controller.
- [x] Guard clauses, no ternaries in the failure paths.
- [x] Port -> implementation bound at the composition root.
- [ ] Tests — **not applicable**, UNIT TESTING POLICY is active.

## Known Limitations

- **The index page's company/contact/pipeline/stage columns resolve their labels
  from the form-options rows, not from the deal row.** The paginated read returns
  flat `DealResponseDTO`s, which carry only the foreign-key ids. Resolving the
  labels needs the options read, which `index()` now also performs. This is a
  deliberate trade-off: one extra four-table read per list page buys a list that
  shows names rather than raw ids. If the list grows, the better fix is a joined
  read model on `DealQueryContract`, which is a schema-level decision for the
  developer. Recorded as D-009.
- The list filter offers the pipeline picker but not the stage picker. A stage
  belongs to one pipeline, so a flat stage picker would let a user filter by a
  stage that cannot occur under the chosen pipeline. `DealFilter` accepts
  `dealStageId` and the API exposes it; only the web form omits it.
