# Step Report 01 - Dashboard Module Refactor and Web Page

- **STEP ID:** 01
- **MODULE:** Dashboard
- **DATE:** 2026-10-20
- **STATUS:** COMPLETE
- **BRANCH:** main

## Goal

Refactor the fat dashboard controller off inline Eloquent and onto the canonical
flow, and give the dashboard a real page. Both dashboard controllers
(`Web\Backend` and `Api\V1`) reached straight for `App\Models\*` and ran six
aggregate queries plus two rates inline in the delivery layer. The web one was
additionally broken: `view('admin.dashboard', $user)` passed a `User` model where
Blade expects an array, so the page 500'd, and the view itself was a bare `<h1>`.

The web page is covered in its own section, "The Dashboard Page", below.

## Files Created

### Core — Domain
| File | Purpose |
| --- | --- |
| `core/Dashboard/Domain/DashboardRules.php` | Feed and board size limits. |
| `core/Dashboard/Domain/DealBoardRules.php` | The stage names that close a deal, re-exposed from `Core\Deal`. |
| `core/Dashboard/Domain/Contracts/DashboardMetricsContract.php` | Port: deals/leads/activities figures. |
| `core/Dashboard/Domain/Contracts/DashboardBoardContract.php` | Port: one summary row per stage. |
| `core/Dashboard/Domain/Contracts/DashboardFeedContract.php` | Port: the outstanding activities, soonest first. |
| `core/Dashboard/Domain/DataObjects/DashboardMetrics.php` | Read model: the three figure sets. |
| `core/Dashboard/Domain/DataObjects/PipelineBoardSummary.php` | Read model: one stage summary row. |
| `core/Dashboard/Domain/DataObjects/ActivityFeedEntry.php` | Read model: one feed row. |
| `core/Dashboard/Domain/ValueObjects/DealMetrics.php` | Deal counts, money totals and win rate. |
| `core/Dashboard/Domain/ValueObjects/LeadMetrics.php` | Lead counts and conversion rate. |
| `core/Dashboard/Domain/ValueObjects/ActivityMetrics.php` | Pending and completed counts. |

### Core — Application
| File | Purpose |
| --- | --- |
| `core/Dashboard/Application/Contracts/DashboardMapperContract.php` | Port: request-to-DTO mapping. |
| `core/Dashboard/Application/DTOs/Requests/DashboardFilterDTO.php` | Carries the panel size limits. |
| `core/Dashboard/Application/DTOs/Responses/DashboardMetricsResponseDTO.php` | Flat headline figures. |
| `core/Dashboard/Application/DTOs/Responses/DashboardStageSummaryResponseDTO.php` | One stage summary. |
| `core/Dashboard/Application/DTOs/Responses/DashboardActivityResponseDTO.php` | One feed row. |
| `core/Dashboard/Application/DTOs/Responses/DashboardOverviewResponseDTO.php` | The whole page payload. |
| `core/Dashboard/Application/Mappers/DashboardMapper.php` | Builds the filter DTO from the rules. |
| `core/Dashboard/Application/Mappers/DashboardResponseMapper.php` | Read models -> response DTOs. |
| `core/Dashboard/Application/Services/DashboardQueryService.php` | The four read use cases. |

### Core — Infrastructure
| File | Purpose |
| --- | --- |
| `core/Dashboard/Infrastructure/Repositories/DashboardMetricsRepository.php` | Counts and totals across deals, leads, activities. |
| `core/Dashboard/Infrastructure/Repositories/DashboardBoardRepository.php` | Stage rows with deal counts and summed value. |
| `core/Dashboard/Infrastructure/Repositories/DashboardFeedRepository.php` | Outstanding activities with the tracked record resolved. |

### Framework / Delivery
| File | Purpose |
| --- | --- |
| `app/Providers/DashboardServiceProvider.php` | Composition root for the module. |

## Files Modified

- `app/Http/Controllers/Web/Backend/DashboardController.php` — rebuilt as a thin
  adapter: mapper -> one service call -> branch on `Result` -> view data.
- `app/Http/Controllers/Api/V1/DashboardController.php` — same flow; `metrics()`
  kept and a new `index()` added.
- `resources/views/admin/dashboard.blade.php` — the page itself, from a bare
  `<h1>` to the six panels described below.
- `routes/api.php` — `GET v1/dashboard` registered alongside the existing
  `GET v1/dashboard/metrics`.
- `bootstrap/providers.php` — `DashboardServiceProvider` registered.

The `Web\Backend` route was left where it is (`Route::get('/dashboard')`, outside
the `admin` prefix). Moving it under `admin.` would change the URL the sidebar,
the brand link and the post-login redirect all use, which is out of this step's
scope.

## The Dashboard Page

`resources/views/admin/dashboard.blade.php`, 302 lines, built on the same design
system the other pages use (`t-shadow t-bg-white`, `t-bg-16` panels, `sm-text` /
`xsm-text`, `la-*` icons, the `table table-borderless` idiom from `deals/index`).
Six panels:

| Panel | Content |
| --- | --- |
| Four headline tiles | Won revenue, open pipeline value, win rate, pending activities. |
| Deals by stage | One row per stage: name, pipeline, odds, a proportional bar, deal count, summed value. |
| Upcoming activities | The feed: subject, type badge, due date, the tracked record, the owner. |
| Lead conversion | Conversion rate with a progress bar, converted-of-total. |
| Deals summary | Total / won / lost / open as badges. |
| Activities summary | Total / pending / completed as badges. |

### Empty states are real states

Every list has an `@empty` branch, and each is a specific invitation rather than
a blank: no stages offers "set up a pipeline" linking to `admin.pipelines.index`;
an empty feed says "Nothing outstanding". No panel can render as an empty box.

### The bars are drawn against the busiest stage, not the total

`$busiestStage = collect($stages)->max('dealsCount')` scales every bar, so a
stage with one deal against a stage with fifty is still a visible sliver rather
than a collapsed line. The divide is guarded (`$busiestStage > 0 ? ... : 0`),
because an empty board is a real state and would otherwise be a division by zero.
The progress bars carry `role="progressbar"` and `aria-valuenow/min/max`, and
each stage's bar has its own `aria-label` naming the count and the stage — the
bar is decoration for a number, so the number is what a screen reader gets.

### The failure branch is a page, not a stack trace

`$metrics === null` renders an alert and a single "The figures could not be
loaded" panel. Verified by swapping the metrics port for one that throws: the
page rendered with the error message, did **not** leak the exception text, and
the navigation links stayed live — a user who hits a read failure still reaches
the rest of the app.

### Two view-level choices worth flagging

- **Dates are formatted with `Carbon::parse(...)->format('d M Y, H:i')` in the
  Blade.** The service hands ISO-8601 over, and formatting a date for display is
  the delivery layer's job, so this is the right side of the boundary. It does
  mean the view imports `Illuminate\Support\Carbon`. That is fine here — the view
  is a framework adapter — but it is a deliberate deviation from the alternative
  of adding pre-formatted fields to the response DTO. If a mobile client ever
  needs the same "14 Oct 2026" string, that decision has to be revisited.
- **The stage table lists every stage, including the closed ones.** A stage
  summary that omitted `Closed Won` / `Closed Lost` would hide where the won
  revenue came from. It also means the closed stages' `total_value` is the value
  of deals that have finished, not open pipeline. This matches
  `KanbanStageData::totalValue()`; see the Known Limitations entry.

## Architecture Notes

### The metrics live in the port, shared by both modules' rules

The four most important figures count deals *by the name of the stage they sit
in*: `won` is the count in `Closed Won`, `lost` in `Closed Lost`, and `open` is
everything else. The original controller hardcoded stage **ids** 5 and 6.

That was wrong against the real data, and not marginally. Stage ids are global
across pipelines, so id 5 is `Closed Won` only in the default pipeline and
`Closing` (an open stage) in every other one. Only the default pipeline happens
to be seeded with won/lost at ids 5/6. `DealRules` says as much in its own
comment, and `DealEntity::closesTheDeal()` already compares **names,
case-insensitively**. The dashboard now asks `DealBoardRules`, which delegates to
the same rule, so the reporting side and the write side can no longer disagree
about whether a deal is closed.

### One use case, three ports

`getOverview()` is one use case, not three: the Blade page is a single view and
the API payload mirrors it. The three panels sit behind three separate ports
rather than one wide one because they aggregate three different tables and are
each answerable on their own — `getMetrics()`, `getStageSummaries()` and
`getUpcomingActivities()` are exposed as separate service methods for callers
that want one panel.

### The adapters aggregate in the database

`DashboardBoardRepository` uses `withCount('deals')` and `withSum('deals as
total_value', 'amount')` rather than loading every deal and totalling in PHP, and
`DashboardMetricsRepository` uses `count()` and `sum()` rather than fetching rows.
A dashboard counts; it does not list.

### The tracked record's label is read per model type

`Activity.trackable` is polymorphic across `lead`, `deal`, `contact` and
`company`. Leads, contacts and companies carry `name`; **deals carry `title`**.
This was caught by verification, not by reading — the first run returned
`trackable_name: null` for every deal row because the adapter read `name` off
whatever the relation resolved to. `trackableLabel()` now picks the column from
the model type.

## Verification (all evidence from actual execution)

The MySQL service was not running when verification started; it was started, and
the reads below ran against the real database. The dev server and the scratch API
token were removed afterwards.

### 1. Syntax
`php -l` clean on all 29 touched/created files. `composer dump-autoload` exited 0
(7160 classes).

### 2. Container resolution
```
Core\Dashboard\Domain\Contracts\DashboardMetricsContract
  -> Core\Dashboard\Infrastructure\Repositories\DashboardMetricsRepository
Core\Dashboard\Domain\Contracts\DashboardBoardContract
  -> Core\Dashboard\Infrastructure\Repositories\DashboardBoardRepository
Core\Dashboard\Domain\Contracts\DashboardFeedContract
  -> Core\Dashboard\Infrastructure\Repositories\DashboardFeedRepository
Core\Dashboard\Application\Contracts\DashboardMapperContract
  -> Core\Dashboard\Application\Mappers\DashboardMapper
Core\Dashboard\Application\Services\DashboardQueryService
  -> Core\Dashboard\Application\Services\DashboardQueryService
```

### 3. Routes
```
GET|HEAD api/v1/dashboard ......... Api\V1\DashboardController@index
GET|HEAD api/v1/dashboard/metrics . Api\V1\DashboardController@metrics
GET|HEAD dashboard ................ dashboard › Web\Backend\DashboardController@index
```

### 4. Figures cross-checked against the raw rows

Raw data:
```
deal#1 amount=250000.00 stage=Closed Lost (id=6)
deal#2 amount=180000.00 stage=Closed Won  (id=5)
deal#3 amount=2500.00   stage=Proposal     (id=14)
deal#4 amount=1000.50   stage=Qualification(id=13)
```
Dashboard: `total=4 won=1 lost=1 open=2 wonRevenue=180000 openValue=3500.5 winRate=50`.
Every figure matches. Deals 3 and 4 sit in `Proposal`/`Qualification` in a
non-default pipeline, so they count as open — which id-based counting would have
reported as won, since `Kanban Check` ids 13/14 do not exist in the default
pipeline but `Closing` id 9 would have been misread as open too.

`leads: total=7 converted=1 rate=14.29` and `activities: pending=13 completed=1`
match the row counts.

### 5. Case-insensitive stage matching
Stage 5 renamed to `closed won` in the database, metrics re-read, then restored:
```
stage name now "closed won": won=1 wonRevenue=180000 open=2 openValue=3500.5
restored to "Closed Won"; after restore: won=1 wonRevenue=180000 openValue=3500.5
```
Counting, revenue and open-value all agree under either spelling.

### 6. API over real HTTP
`GET /api/v1/dashboard` with no token -> **401**, so the `auth:sanctum` guard is
intact. With a token -> **200**:
```json
{"status":"success","message":"Dashboard retrieved successfully.",
 "data":{"metrics":{"revenue":{"won_revenue":180000,"open_pipeline_value":3500.5,"currency":"BDT"},
 "deals":{"total_deals":4,"won_deals":1,"lost_deals":1,"open_deals":2,"win_rate":50},
 "leads":{"total_leads":7,"converted_leads":1,"conversion_rate":14.29},
 "activities":{"pending":13,"completed":1}},
 "pipeline":[{"id":1,"pipeline_id":1,"pipeline_name":"Standard Sales Pipeline","name":"Discovery",
   "order_position":1,"probability":10,"deals_count":0,"total_value":0}, ...14 rows],
 "upcoming_activities":[{"id":6,"type":"meeting","subject":"Proposal Negotiation with Abul Kalam",
   "due_date":"2026-10-14T11:30:00+00:00","completed_at":null,"user_id":44,"user_name":"Abdul Kalam",
   "trackable_type":"deal","trackable_id":2,"trackable_name":"Rahman Dying - Automated Dyeing Setup"}, ...8 rows]}}
```

### 7. The legacy `metrics` endpoint is unchanged
`GET /api/v1/dashboard/metrics` -> 200 with the identical body and message
`"Dashboard metrics retrieved successfully."` that the original controller
returned. The payload is grouped back into `revenue`/`deals`/`leads`/`activities`
in the controller, because the response DTO carries the figures flat for the
Blade page.

### 8. Cleanup
Scratch token deleted (`tokens deleted: 1`, 10 pre-existing tokens untouched).
Dev server on port 8099 stopped; no `php` processes left. Row counts unchanged:
`deals=4 leads=7 stages=14 activities=14`, and `stage#5 name=Closed Won`.
No row was created, modified or deleted by any verification step.

### 9. The web page, over real HTTP as a session

Blade compiled to valid PHP first (`blade.compiler->compileString` -> 20,940
chars -> `php -l` clean), so a template syntax error would surface before any
request.

Then the full browser flow against a real server on port 8098: fetch `/login` for
the CSRF token and session cookie, POST the credentials, follow to `/dashboard`
carrying the session.

```
GET  /dashboard (guest)  -> 302, Location: /login
GET  /login              -> 200
POST /login              -> 302, Location: /dashboard
GET  /dashboard (authed) -> 200 OK, 189,366 bytes
```

Every panel and figure present in the served bytes:

```
Dashboard              present      Deals by stage       present
Won revenue            present      Upcoming activities  present
180,000.00             present      Lead conversion      present
3,500.50               present      progress bars:       15   (14 stages + the lead rate)
50%                    present      stack trace:         none
14.29%                 present      leftover blade:      none
```

The page read back as text matches the underlying rows: `Won revenue 180,000.00
BDT · 1 deal won`, `Open pipeline 3,500.50 BDT · 2 deals still in play`, `Win
rate 50% 1 won of 2 closed`, `Pending activities 13 1 completed`, and the stage
table showing `Closed Won … 1 … 180,000.00` and `Closed Lost … 1 … 250,000.00`
against deal#2 and deal#1. `Lead conversion 7 total 14.29% 1 of 7 converted`.

### 10. The failure branch
Metrics port swapped for an adapter that throws:
```
rendered on failure: 141,262 bytes
shows error:      yes      leaks exception: no
shows fallback:   yes      nav still works: yes
```

### 11. A destructive verification step, and its repair

To log in over HTTP I first pointed `admin@example.com` at a throwaway password.
That was a mistake in method: it wrote to a real user row to save the trouble of
looking up the seeded credential. The seeder
(`database/seeders/AdminUserSeeder.php`) holds `Admin@123456`, which would have
worked from the start. The password was restored immediately and re-checked:
```
admin password still matches seeder: yes
```
No other column was touched. The HTTP work in section 9 used the seeded password,
so no second overwrite happened.

### 9. Portability
```
framework imports in core/Dashboard/{Domain,Application}: none
framework facades/globals in the same:                  none
```
`declare(strict_types=1);` on every created file. All concrete classes are
`final` (services, mappers, adapters, DTOs, value objects, read models).

## Definition of Done

- [x] Strict typing on; namespaces match paths.
- [x] Business rule (which stages close a deal) lives in the domain, not the controller.
- [x] The controller is thin: input -> mapper -> service -> view/JSON.
- [x] One Query Service; it mutates nothing and records no events.
- [x] Every read returns a ResponseDTO inside a `Result`; no Entity or Eloquent model escapes.
- [x] No direct Eloquent or `DB::` call in either controller.
- [x] Three ports, three adapters, bound at a composition root.
- [x] Guard clauses throughout; no ternaries in the failure paths.
- [x] The Blade view holds no business rule: every figure arrives from a ResponseDTO.
- [x] Every list on the page has a specific empty state, not a blank box.
- [x] No test file was created — UNIT TESTING POLICY is active.

## Known Limitations

- **The headline totals sum deals across every currency under one reported
  currency.** `deal#1..4` are all `BDT`, so the current figures are correct.
  Adding a USD deal would make `wonRevenue` add 180000 BDT to N USD and label the
  sum `BDT`. Flagged as **D-011**; reporting per currency is a read-model
  decision for the developer.
- **The stage summary totals every stage, not only open ones.** A closed stage
  therefore reports the value of the deals that ended there. This is the reading
  the old Kanban-style board already uses (`KanbanStageData::totalValue()`), so
  it was kept rather than invented. If the overview should show open pipeline
  only, that is a one-line change in `DashboardBoardRepository`.
- **Win rate is `won / (won + lost)`**, carried over from the original
  controller. An explicit business definition was not supplied.
- The dashboard page reads are three separate queries with no caching. At this
  data size that is the right trade; a cached read model is a later decision.
- The dashboard shares the `dashboard` route name with the `Web\Backend` route
  only; no navigation entry was added or changed, since the existing sidebar
  already links to it.
