# Step 01 — Dashboard: Actionable Cockpit (design)

**Module:** dashboard
**Step:** `dashboard/01-actionable-cockpit`
**Status:** DONE_WITH_NOTES — **design only. No application code was written.**
**Decisions:** D-145 … D-154

---

## 0. What this step is, and what it deliberately is not

The brief was delivered as an "AI Assistant Prompt" naming an objective (turn the Dashboard from a
static report into an actionable cockpit), four priority zones, a set of architectural mandates, and
four required deliverables — **Layout Blueprint, DTO Definition, Service Signature, Mapping
Strategy**.

All four deliverables are design artifacts, so this step produces a design and writes no code. Three
things in the brief could not be built as written because their premises do not hold against the
repository, and one of them was put to the developer, who chose to defer it:

| Brief item | Status |
| --- | --- |
| Add "Bids"/"Proposals"/"Negotiation" to `leads.php` | **Deferred by the developer** — no data model exists (D-145) |
| "Conversion Velocity" — avg. New → Converted time | **Not computable** — neither endpoint is observable (D-148) |
| Create `DashboardProjectionDTO` / `DashboardResponseDTO` / `DashboardRepository` | **Already exist under other names** — creating them would be the duplicate-file violation (D-151) |
| Zones A–D content, layout, DTOs, signatures, mapping | **Designed** — §2–§6 |

**Nothing in this report is verified against generated code, because none was generated.** The
self-check in §8 audits the *existing* files the design rests on, and every claim about live data is a
measured query result, not an estimate.

---

## 1. What exists today (audited, not recalled)

The Dashboard module is **read-only** and already satisfies most of the brief's architectural
mandates. Anyone building this step must not re-create any of the following.

```
core/Dashboard/
  Application/
    Contracts/   DashboardMapperContract.php  DashboardQueryServiceContract.php
    DTOs/Requests/   DashboardFilterDTO.php          (feedLimit, boardLimit)
    DTOs/Responses/  DashboardOverviewResponseDTO.php
                     DashboardMetricsResponseDTO.php
                     DashboardStageSummaryResponseDTO.php
                     DashboardActivityResponseDTO.php
    Mappers/     DashboardMapper.php  DashboardResponseMapper.php
    Services/    DashboardQueryService.php
  Domain/
    Contracts/   DashboardMetricsContract.php   DashboardBoardContract.php
                 DashboardFeedContract.php
    DataObjects/ DashboardMetrics.php  PipelineBoardSummary.php  ActivityFeedEntry.php
    ValueObjects/ DealMetrics.php  LeadMetrics.php  ActivityMetrics.php
    DashboardRules.php   (FEED_LIMIT = 8, BOARD_LIMIT = 12)
    DealBoardRules.php
  Infrastructure/Repositories/
    DashboardMetricsRepository.php  DashboardBoardRepository.php  DashboardFeedRepository.php
```

Delivery: `app/Http/Controllers/Web/Backend/DashboardController.php` (Blade) and
`app/Http/Controllers/Api/V1/DashboardController.php` (JSON). View:
`resources/views/admin/dashboard.blade.php`. **There is a `tests/Feature/Dashboard/` directory in the
repository, but it contains no files** — testing is paused and nothing was touched.

**Already compliant with the brief's mandates:**

- `DashboardQueryService` is read-only, `final`, `@implements DashboardQueryServiceContract`, returns
  `Result` around a ResponseDTO, and opens no transaction. It has never had a command counterpart, so
  the CQS split is not merely respected — it is structural.
- `getOverview()` already returns everything in **one call**. The "avoid multiple HTTP requests"
  requirement is met today.
- All three read ports live in `Domain/Contracts/` and all three adapters are framework-backed
  (`App\Models\*`, `Illuminate\Support\Facades\DB`), declared `final class` with **no** `extends` clause
  and no write helpers. Correct per AGENTS.md *(WRITE AND READ PORT PLACEMENT)*; Dashboard has no
  `RepositoryContract` and must not grow one.
- No `Illuminate\*` import exists anywhere in `core/Dashboard/Domain` or `core/Dashboard/Application`
  (verified: 0 matches).

**And one thing that is NOT compliant.** Both dashboard controllers inject the **concrete**
`Core\Dashboard\Application\Services\DashboardQueryService` instead of
`DashboardQueryServiceContract`:

```php
// app/Http/Controllers/Web/Backend/DashboardController.php:10, 22-23
use Core\Dashboard\Application\Contracts\DashboardMapperContract;
use Core\Dashboard\Application\Services\DashboardQueryService;   // ← the concrete class

public function __construct(
    private readonly DashboardQueryService $dashboardQuery,      // ← not the contract
    private readonly DashboardMapperContract $mapper,
) {}
```

AGENTS.md **INBOUND SERVICE CONTRACTS** requires every delivery adapter to constructor-inject the
contract and forbids type-hinting the concrete Service. The web controller imports
`DashboardQueryServiceContract` in its docblock but not in its `use` list, so the import is dead and the
hint is the class. **Both** controllers are affected. The fix is a two-line change per file and needs no
behavioural change — but it is outside this step's scope, so it is recorded, not fixed (report §7.9).

**Two facts that shape the design:**

1. **`DashboardStageSummaryResponseDTO` carries no stage id** even though `PipelineBoardSummary` has
   one, and the Blade already groups by pipeline. `PipelineBoardSummary` has
   `id, pipelineId, pipelineName, name, orderPosition, probability, dealsCount, totalValue` — no FK to
   the `leads` that produced the deals.
2. **The money rule is violated in one place.** AGENTS.md **P2** says money is a `string`, never a
   `float`; `DashboardMetricsResponseDTO` declares `float $wonRevenue` and `float $openPipelineValue`,
   and `PipelineBoardSummary` declares `float $totalValue`. This predates the step. See §7.

---

## 2. Deliverable 1 — Layout Blueprint

### 2.1 The organising rule

The brief's goal is to shorten the time between seeing data and acting on it. The current page opens
with **four vanity tiles** and puts **13 overdue items below the fold**. The redesign inverts that:
**urgency first, vanity metrics quietened**, and every urgent row carries the write action it needs.

The page becomes four bands, in this order.

---

### Band 0 — Header

Unchanged from today: badge, title, and the two "all deals" / "all leads" buttons.

---

### Band 1 — Zone B: Action Center (was a side panel; promoted above the fold)

This is the deliberate departure from the current layout. Zone B is the "reduce time to action" zone,
so it leads.

**1a. Overdue activities** — the live list, no longer labelled "Upcoming". With unlimited threshold:

> **Overdue** `13 overdue · 0 due soon` — *"13 means the backlog is the whole list; set a lookback
> window or the panel is a permanent wall of red."* **Risk of alert fatigue; the threshold is UNSET
> (D-147 / D-154).**

Each row: subject, due date rendered **in UTC** with the age in days, the tracked record, the owner,
and a **Done** action.

**1b. Stale leads** — leads sitting in one status too long. Threshold **UNSET (D-147)**. When unset the
panel renders *"Not configured — set a stale threshold in Dashboard rules."* and **never an empty
list**, because an empty list asserts "nothing is stale", which with no threshold is unfalsifiable.

**1c. Quick Actions** — the three buttons, each bound to an action that already exists:

| Button | Existing target | Missing piece |
| --- | --- | --- |
| **Assign me the next lead** | `LeadOwnerCommandServiceContract::assignOwner()` | needs an "unassigned, most urgent" read |
| **Log an activity** | `admin.activities.create` (already linked from the lead quick-action bar) | none |
| **Work the overdue list** | the 1a list, anchored | none |

The "assign me" button resolves the current user **in the controller** and passes it as
`requestedBy`/`assignedTo` — Core never reads session state (P14). Its label says "next unassigned
lead", not "new lead": **all 11 leads in the live database are unassigned**, including 3 `converted`
and 2 `contacted`, so "new" would be a false description of what the button hands over (D-149).

---

### Band 2 — Zone A: The Pulse

Four tiles, and — following the dataviz method — **they are not four equal boxes**. A priority band
with no priority is decoration.

**Quarter 1 — the two money figures that exist today.** "Won revenue" and "Open pipeline". No change
except the currency label, which must state that the figure sums every pipeline under one currency
(**P6** — the live data is single-currency today, `BDT ×4`, but `deals.currency` is indexed and
nullable-free, so multi-currency is one insert away).

**Quarter 2 — two new, heavier tiles.**

**Tile: New Leads (Uncontacted).** `status = 'new'` → **5**. Defined that way, and only that way
(D-146). The tempting alternative — "leads with no completed call, meeting or email" — is **not**
offered as the primary number because it contradicts the live data: all 5 `new` leads already carry
activities, so the "truly untouched" count is not 5 and the label would be lying. It is available as a
separate, honestly-named secondary figure: *"New, already touched — the queue that looks fresh and
isn't."*

**Tile: Conversion Velocity.** **Cannot be built as specified (D-148).** Neither endpoint is
observable:

- "Left New" is recorded nowhere — no `status_changed_at`, no history table, and `updated_at` is
  written by every edit.
- The brief's "Contacted" and "Qualified" are **not distinct states in the database**: `leads.status`
  is created with `->default('new')->index()` and indexes exactly one value. Only `new` exists in the
  schema; the other four are application-level values the plan describes and the migrations never
  declare.

The only computable quantity is `lead_conversions.converted_at − leads.created_at` — capture to
conversion, which is *not* the requested metric and should not carry its name. The live database holds
exactly **one** conversion: **219 h ≈ 9.1 days**, and that row is almost certainly a probe.

**Recommendation:** ship Band 2 with a fourth tile that *is* computable — *"Lead conversion rate"*,
which the current page already computes, promoted from the side panel — and put "velocity" behind the
`status_changed_at` work as its own step.

---

### Band 3 — Zone C: Pipeline Funnel, with leakage

Today the board is a flat table per pipeline (tabs), showing stage / deals / value / probability.

**Change 1 — make it a funnel.** Draw each pipeline top-to-bottom with a decreasing width and a
**per-stage retention percentage** against the entry stage. The width is the funnel; the percentage is
the story.

**Change 2 — leakage, with the honest cap.** "Where are leads lost" **cannot be answered**, because
conversion is not modelled as a stage: a lead leaves via `status = 'lost'` from `contacted` or
`qualified`, so "40% drop-off at Qualification" has no `lost_at_stage` to read. Today's data has only
`{contacted: 2, converted: 3, new: 5, qualified: 1}` — **zero** `lost` leads, so any leak percentage
either refuses to compute or reports "0 lost" for a funnel nothing has ever exited.

What *is* answerable and should be shipped instead: **"Deals lost by stage"** — count deals whose stage
name is the lost stage, per pipeline (the only deal is in `DealBoardRules::LOST_STAGE_NAME`). It states
what it measures rather than claiming lead drop-off.

**Change 3 — the honest lead funnel.** With one state in the schema, "New → Contacted → Qualified →
Converted" is *not* a funnel the database has. Drawing it as one would be five bars, four of which are
the same row. **Recommendation:** keep the deal funnel from the `deals`/`deal_stages` tables, which
genuinely does have ordered stages, and present lead movement as a **rate**, not a funnel.

---

### Band 4 — Zone D: Lead-to-Deal Bridge

Keep the conversion percentage; it is correct and already computed as `converted / total`.

**"Best performing source" — designed, deliberately left undefined (D-153).** `leads.source` is free
text with no FK and no enum, and the live data has **7 distinct values across 11 rows**, five of them
prose:

| Source | Leads |
| --- | --- |
| `landing_page` | 3 |
| `postman` | 3 |
| `probe` | 1 |
| `website` | 1 |
| 63-char Latin lorem sentence | 1 |
| 77-char Latin lorem sentence | 1 |
| 97-char Latin lorem sentence | 1 |

**Any of the three singleton lorem rows wins outright on raw percentage, so a naive `MAX` would
report a made-up sentence as the best channel.** Whichever reading is chosen, the panel must apply a
**minimum sample floor** and print `n` beside every rate, or it is a random number generator with a
trophy icon.

The two readings — highest conversion **rate** (needs the floor) and highest conversion **count**
(needs none) — point at different features, so this is put to the developer rather than guessed.

---

## 3. Deliverable 2 — DTO definition

Two changes. Both **additive**.

### 3.1 Existing DTOs — unchanged

`DashboardOverviewResponseDTO`, `DashboardMetricsResponseDTO` and the two child DTOs keep every
property, name and casing they have today. This is what makes the change wire-safe: the API
controller's `formatMetrics()` and `formatStage()` read those properties by name, and
`resources/views/admin/dashboard.blade.php` reads `$metrics->wonRevenue` etc. Renaming any of them is a
breaking API change (P16). **No rename is proposed anywhere in this step.**

### 3.2 New: `DashboardActionCenterResponseDTO`

```
core/Dashboard/Application/DTOs/Responses/DashboardActionCenterResponseDTO.php
    final readonly class DashboardActionCenterResponseDTO
        public array  $overdueActivities     // list<DashboardActivityResponseDTO>
        public array  $dueSoonActivities     // list<DashboardActivityResponseDTO>
        public array  $staleLeads            // list<DashboardUrgentLeadResponseDTO>
        public ?int   $unassignedLeadId      // the "Assign me" button's target, null when none
        public int    $unassignedLeadCount
        public bool   $staleThresholdsConfigured
        public int    $overdueLookbackDays   // 0 when unset
```

`staleThresholdsConfigured` is the flag that lets the view say "not configured" instead of "nothing is
stale" (D-147). It is a boolean because the alternative — inferring "unset" from an empty list — is
exactly the unfalsifiable claim the flag exists to prevent.

### 3.3 New: `DashboardUrgentLeadResponseDTO`

```
    final readonly class DashboardUrgentLeadResponseDTO
        public int      $id
        public string   $name
        public string   $status
        public string   $status_label
        public ?string  $assigned_to_name
        public int      $days_in_status
        public ?string  $last_activity_at     // ISO-8601 UTC
```

`status` is the raw enum value and `status_label` the translated text — the same pair
`LeadResponseMapper` already emits, so the view formats nothing.

### 3.4 Read models (Domain), not DTOs

Per the layering already in place, the *read models* stay in `Domain/DataObjects/` and the *response
DTOs* in `Application/DTOs/Responses/`. New read models:

- `Dashboard\Domain\DataObjects\UrgentLeadEntry` — mirrors §3.3.
- `DashboardActionCenter` — mirrors §3.2, holding `UrgentLeadEntry` objects.

**They are deliberately not called `DashboardProjectionDTO`.** That name belongs to a class the brief
believes does not exist; the projection role is occupied by `DashboardMetrics`, and creating a second
file at the same responsibility is the FILE EXISTENCE GUARD violation (D-151).

---

## 4. Deliverable 3 — Service signature

`DashboardQueryServiceContract` gains **one** method. Nothing is renamed and nothing is removed.

```php
/**
 * Returns the urgency panel: overdue and due-soon activities, the leads
 * sitting too long in one status, and the lead the "assign me" action
 * should offer next.
 *
 * @param DashboardFilterDTO $dashboardFilterDTO
 *
 * @return Result Contains the DashboardActionCenterResponseDTO on success.
 */
public function getActionCenter(DashboardFilterDTO $dashboardFilterDTO): Result;
```

**Why one new method rather than a wider `getOverview()`.** `getOverview()` is the page payload; the
action center is a *panellable* read the same way `getMetrics()`, `getStageSummaries()` and
`getUpcomingActivities()` already are. Adding a fifth panel read follows the module's existing shape
exactly, and it keeps `getOverview()` additive: the overview contract grows one `actionCenter` field
(§3.1) and the one-request requirement still holds, because `getOverview()` calls this method
internally.

**The write side is not in this module, and must not be.** Per the brief's own CQS constraint and
P7, the actions call **Lead's** existing contracts:

```php
// Already exists — do not rebuild:
LeadOwnerCommandServiceContract::assignOwner(LeadOwnerAssignDTO $dto): Result
LeadCommandServiceContract::…                        // status transition via admin.leads.update
```

**No new command service, no new dashboard route.** `admin.leads.update` already runs the brief's
exact flow — Controller → Mapper → `LeadCommandService` → `LeadEntity::transitionTo()` →
`LeadTransitions::can()` → write repository — inside a transaction, returning a `Result`. A second
route to the same use case would be a second entry point to one port. **This needs the developer's
confirmation** (D-150), because the wiring exception in AGENTS.md covers only a *newly created* route.

`DashboardFilterDTO` gains the two threshold fields, still built by `DashboardMapper` from
`DashboardRules` and never from request input — the existing reason holds unchanged ("a client cannot
ask the feed for more rows than the panel is built to show").

---

## 5. Deliverable 4 — Mapping strategy

The rule the whole design follows: **aggregate in SQL, flatten in the repository, thin the mapper.**

```
raw SQL aggregates
   → Infrastructure   DashboardActionFeedRepository
                      (Eloquent/DB, returns read-model objects, extends NOTHING)
   → Application      DashboardResponseMapper::toActionCenterResponseDTO()
                      (read model → ResponseDTO; no DB access, no query building)
   → Presentation     Blade prints scalars; the API controller re-groups into JSON keys
```

**5.1 Count only, never hydrate.** Every new figure is a `COUNT`/`MIN`/`MAX` or a bounded
`LIMIT 8` select. No branch of this design loads a collection to count it, and the "next unassigned
lead" is `LIMIT 1`, not a sorted list.

**5.2 Urgency ordering is a domain rule, so it lives in the Domain.** "Which lead is most urgent" is
`CASE status WHEN 'new' THEN 1 WHEN 'contacted' THEN 2 … END` — a business rule. It becomes a
`Dashboard\Domain\DashboardRules` (or `UrgentLeadRules`) constant/method, **not** an inline SQL string,
mirroring how `LeadTransitions` owns the transition table. Same for `days_in_status`.

**5.3 Null-vs-zero at the boundary.** With no stale leads, `staleLeads = []` and
`staleThresholdsConfigured = false`. The mapper must never collapse "unset" into "zero".

**5.4 `days_in_status` uses `updated_at` and must say so.** There is no transition timestamp (D-148),
so the honest available proxy is `TIMESTAMPDIFF(DAY, updated_at, now())`. It is a **proxy**: any name,
email or owner edit resets it. The DTO field should carry that in its docblock, and the panel tooltip
should read "no change for N days" rather than "in this status for N days".

**5.5 UTC everywhere in the new read.** Overdue styling computes against UTC. Note the existing bug
this design must not inherit: `toAtom()` emits UTC, but the Blade parses with
`Carbon::parse($activity->due_date)` and **no timezone**, so it renders in `APP_TIMEZONE` (D-154).

---

## 6. Definition of Done for the *build* step

Not claimed here — this step built nothing. When the build step runs, it must not be reported done
without:

- `DashboardActionCenterResponseDTO` and its read models created; `DashboardOverviewResponseDTO`
  widened by exactly one field; **no existing DTO property renamed** (P16).
- The new adapter extends **nothing** and the new port sits in `Application/Contracts/` if its returns
  are Application types, or `Domain/Contracts/` if they are Domain read models (this design: Domain
  read models → `Domain/Contracts/`, matching `DashboardBoardContract`).
- `AppServiceProvider`/`DashboardServiceProvider` binds the new port; `optimize:clear`; autoload
  regenerated.
- The Blade prints **no** database query, no `auth()->user()` and no business branch (P14).
- `php -l` clean on every touched file; the web page and `/api/v1/dashboard` both 200 with the existing
  keys intact.
- **No test may be claimed** — testing stays paused.

---

## 7. Items found during this audit that are NOT this step's to fix

Recorded, not fixed. Each is a real defect; none is in scope.

| # | Finding | Evidence | Severity |
| --- | --- | --- | --- |
| 1 | **P2 violation: money is typed `float`.** `DashboardMetricsResponseDTO::$wonRevenue`, `$openPipelineValue` and `PipelineBoardSummary::$totalValue` are `float`; AGENTS.md P2 mandates `string` | the two files above | Medium |
| 2 | **`DashboardStageSummaryResponseDTO` drops the stage `id`** that its read model carries, so the view cannot key a stage | `DashboardStageSummaryResponseDTO` vs `PipelineBoardSummary::$id` | Low |
| 3 | **`BOARD_LIMIT` lives in the Domain but is consumed in the Blade view** — grouping, capping and default-pipeline selection are all in `dashboard.blade.php` | `dashboard.blade.php` `@php` block; already recorded as D-140 open item 5 | Low |
| 4 | **All 5 `new` leads already have activities**, so "uncontacted" cannot mean "untouched" | live query | Design input (D-146) |
| 5 | **All 11 leads are unassigned**, including 3 converted — "assign me a new lead" is not the cohort the label implies | live query | Design input (D-149) |
| 6 | **`DashboardFeedRepository::trackableLabel()` states the trackable label column with an inline `instanceof` check** rather than a table or map, so adding a trackable model means editing that read adapter | `DashboardFeedRepository::trackableLabel()` | Low |
| 7 | **`docs/ai-plans/lead/lead_module_plan.md` §1 declares lead columns that do not exist** (`first_name`, `last_name`, `notes`, `deleted_at`) | migration vs plan; already D-116 | Medium (D-148) |
| 8 | **Activity due dates render in the wrong timezone** — UTC emitted, parsed without a timezone | `toAtom()` + Blade `Carbon::parse()` | Low (D-154) |
| 9 | **Both dashboard controllers inject the concrete `DashboardQueryService`** instead of `DashboardQueryServiceContract`, against AGENTS.md *INBOUND SERVICE CONTRACTS* | `app/Http/Controllers/Web/Backend/DashboardController.php:10,22` and `app/Http/Controllers/Api/V1/DashboardController.php:9,20` | Medium |
| 10 | The **`DashboardMapperContract` is declared and bound but adds nothing** — it exposes one method with no parameters, reading two constants. It is the only mapper contract in the module set whose mapping is entirely parameterless | `DashboardMapper` / `DashboardMapperContract` | Low |

---

## 8. Self-check (audited against the real files, not from memory)

**A. Files read in full for this step.** All 12 `core/Dashboard/**` classes; both controllers; the
Blade view; `DashboardRules`; `DealBoardRules`; `LeadStatus`; `LeadTransitions`; `LeadEntity` (head);
`LeadQueryContract`; `LeadOwnerCommandServiceContract` + `LeadOwnerAssignDTO`; `Result`;
`PaginatedResult`; `LeadProjectionDTO`; `TranslatorContract` + `LaravelTranslator`;
`resources/lang/en/leads.php`; the `leads`, `deals`, `deal_stages`, `pipelines`, `activities` and
`lead_conversions` migrations; `docs/ai-plans/00-MASTER-ROADMAP.md` (empty);
`docs/ai-plans/lead/lead_module_plan.md`; `docs/ai-plans/lead/lead_details_plan.md`;
`docs/ai-reports/NEXT-SESSION.md`; `DECISIONS.md` D-101…D-144.

**B. Claims verified by execution.** A bootstrapped Laravel probe against the live `lead_bridge_db`
printed: leads by status `{new:5, contacted:2, qualified:1, converted:3, lost:0}` and **11/11
unassigned**; **7** distinct `source` values; activities `{overdue:13, upcoming:0, no-date:0,
done:1}`; 3 stages holding deals across 2 pipelines; **1** conversion at **219 h**; **1** currency
(`BDT`). Every number quoted in this report is from that output.

**C. Mandate conformance of the *design* (the code it will produce).**

| Mandate | Where the design satisfies it |
| --- | --- |
| CQS split | New method is on the **query** contract; the write side is Lead's existing command contracts. No dashboard command service. |
| Both service contracts exist | Dashboard has a query contract and needs no command contract — it performs no writes. Unchanged. |
| Read port placement | New port is read-only, lives in `Domain/Contracts/` beside `DashboardBoardContract` because its model is a Domain read model. |
| Read adapter extends nothing | Stated explicitly in the DoD (§6). |
| No vendor exception in Core | No new port can raise one; the only failure path is the existing `\Throwable` catch in the service. |
| Port method naming | `getActionCenter()` / `getOverdueActivities()`-style verbs already used in the module. |
| Framework-portable Core | No `Illuminate\*`, no `now()`, no `Carbon` added. |
| P2 money | **Not satisfied by existing code** — recorded in §7 item 1. The design adds no new float money. |
| P6 mixed currency | The currency label already states the figure's scope; the design keeps it and adds no bare `SUM`. |
| P7 cross-module reporting | The new reads stay on Dashboard's own ports, exactly as `getDefaultPipelineId()` did (D-139). |
| P14 current user | Resolved by the controller, passed as `requestedBy`. |
| P16 wire contract | No existing property renamed. |
| Localization | New user-facing text goes through `TranslatorContract` / lang files; no literal in Core. |
| FILE EXISTENCE GUARD | §1 lists every existing artifact; §3 states the new files, none of which exist. |

**D. What is NOT verified, stated plainly.**

- No code was written, so there is no diff to audit and no lint to report.
- The Blade layout in §2 is a **specification**, not a rendering. It has not been viewed in a browser,
  and Bootstrap 5.0.1 is in force with the jQuery plugin bridge **gone** (D-144) — any JS added later
  must guard `typeof $.fn.X === 'function'`.
- The new SQL is **not written**, so its performance is unmeasured. The live dataset is tiny (11 leads,
  4 deals, 14 activities); no index recommendation is made on this sample, because a recommendation
  from 11 rows would be noise.
- `Dashboard\Domain\DataObjects\UrgentLeadEntry` and `DashboardActionCenter` were checked for
  non-existence **by directory listing**, not by a full-tree search.

**E. Three claims from the first draft of this report were wrong and are corrected in place.**

| Claim as first drafted | Correction |
| --- | --- |
| The bid/proposal/negotiation search returned "zero matches" | The substring `bid` matches **8** times, all inside `jobIdentifier`, plus one prose comment. The conclusion holds — nothing matches the *bidding flow* — but "zero" was overstated |
| `DashboardFeedRepository:131-133` mutates a model | **False.** The file is **108 lines**. The cited lines were from `DashboardMetricsRepository`, which I had read immediately beforehand; I carried the line numbers across files. There is no model mutation in the feed repository |
| "Conversion Velocity cannot be built" | True, but incomplete until the status enum was re-read: the schema indexes **exactly one** status value, so four of the brief's five statuses are not stored states at all |

---

## 9. Open questions for the developer

1. **Stale thresholds** (D-147). Values for *days in status* and *days overdue*, and whether they
   belong on the Dashboard rules or on a Lead-owned rules class.
2. **Velocity** (D-148). Report capture-to-conversion under an honest name now, or defer until
   `status_changed_at` + a status-history table are built?
3. **Best source** (D-153). Rate or count, and what minimum sample floor gates a rate?
4. **Route** (D-150). Confirm reuse of `admin.leads.update` rather than a new dashboard endpoint.
5. **"Assign me"** (D-149). Most urgent unassigned lead, or strictly `status = new`?
6. **Bids / Proposals / Negotiation** (D-145). Deferred — but the underlying flow does not exist. If it
   is meant to exist, it needs its own step and its own data model.
