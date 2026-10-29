# Next Session

Date written: 2026-10-29
Updated by: `dashboard/01-actionable-cockpit` step (report `dashboard/01-actionable-cockpit.md`)

---

## Most recent work — Dashboard Actionable Cockpit, DESIGN ONLY (report `dashboard/01-actionable-cockpit.md`)

**No application code was written.** The step produced the four requested deliverables — layout
blueprint, DTO definition, service signature, mapping strategy — as a design. Decisions **D-145 … D-154**.

**Read the report before building anything.** It lists, per file, what already exists, so the build step
does not re-create it.

### What the design says, in one paragraph

Add `actionCenter` to the existing `DashboardOverviewResponseDTO` and one method `getActionCenter()` to
`DashboardQueryServiceContract`. Move Zone B (Action Center) **above** the four vanity tiles — today the
page opens with `Won revenue` / `Open pipeline` / `Win rate` / `Pending activities` while **13 overdue
activities sit below the fold**. Rename the "Upcoming activities" panel, which is already the overdue
list. Every new action calls an **existing** Lead command contract: **no dashboard command service, no
new dashboard route**. **No existing DTO property is renamed**, so `/api/v1/dashboard` and the Blade
page keep every key they have today.

### Four premises in the brief did not hold — do not rebuild against them

| Premise | Reality |
| --- | --- |
| Add "Bids / Proposals / Negotiation" to `leads.php` | **No data model.** 5 lead statuses, no round/version column, zero grep hits. Developer deferred it. (D-145) |
| "Conversion Velocity" = avg. New → Converted | **Not computable.** No `status_changed_at`, no history table. Worse: the schema indexes **exactly one** status value (`->default('new')->index()`), so the brief's "Contacted"/"Qualified" are **not stored states**. (D-148) |
| Create `DashboardProjectionDTO` / `DashboardResponseDTO` / `DashboardRepository` | **All three roles already exist** — as `Domain\DataObjects\DashboardMetrics`, `DashboardOverviewResponseDTO`, and the three read adapters. Creating them = duplicate-file violation. (D-151) |
| "Best performing source" | `leads.source` is free text: **7 values across 11 rows, 5 of them prose.** A naive `MAX` reports a lorem-ipsum sentence as the best channel. Left undefined deliberately. (D-153) |

**This is the third occurrence of "the brief's premise is stale"** (after D-137 and D-141). D-151
proposes an AGENTS.md line for it.

### Two live-data measurements that changed the design

- **All 5 `new` leads already carry activities.** "Uncontacted" therefore cannot mean "never touched".
  Defined as `status = 'new'`, and the activity-based reading is offered as a separate, honestly-named
  figure. (D-146)
- **All 13 pending activities are overdue; zero are upcoming.** So the existing "Upcoming activities"
  panel is already the overdue list, oldest first, truncated at `FEED_LIMIT = 8`. (D-154)

**Probe recipe used** (works, reuse it): bootstrap Laravel in-process and run raw aggregates —

```php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// then DB::select / DB::selectOne
```

Database is `lead_bridge_db`. `shell_exec('php artisan tinker')` does **not** work here (environment
note 1).

### Ten defects found and NOT fixed (report §7)

Highest-value: **(9) both dashboard controllers inject the concrete `DashboardQueryService`** instead of
`DashboardQueryServiceContract`, against AGENTS.md *INBOUND SERVICE CONTRACTS* — a two-line fix in each
file, outside this step. **(1) P2 violation** — `DashboardMetricsResponseDTO::$wonRevenue`,
`$openPipelineValue` and `PipelineBoardSummary::$totalValue` are `float` where AGENTS.md P2 mandates
`string`. **(7) `docs/ai-plans/lead/lead_module_plan.md` §1 declares lead columns that do not exist**
(`first_name`, `last_name`, `notes`, `deleted_at`) — the drift already filed as D-116, and the same
drift that made the brief's status vocabulary wrong.

**Five claims from this report's first draft were checked and corrected in place** (report §8.E). One was
material: I attributed a model mutation to `DashboardFeedRepository` at lines 131-133, but that file is
108 lines — I had carried line numbers across from `DashboardMetricsRepository`, which I read
immediately before. Also corrected: `tests/Feature/Dashboard/` **exists but contains no files**, not
"3 files", and the bid/proposal/negotiation search is "zero matches for the flow", not "zero matches"
(the substring `bid` hits 8 times inside `jobIdentifier`).

---

## The six questions blocking the build

| # | Question | Decision |
| --- | --- | --- |
| 1 | Stale-lead thresholds — days in status, days overdue. On Dashboard rules or Lead rules? | D-147 |
| 2 | Velocity — report capture-to-conversion under an honest name now, or defer until `status_changed_at` + history table exist? | D-148 |
| 3 | Best source — conversion **rate** or **count**? What minimum sample floor gates a rate? | D-153 |
| 4 | Confirm reusing `admin.leads.update` rather than adding a dashboard endpoint | D-150 |
| 5 | "Assign me" — most urgent **unassigned** lead, or strictly `status = 'new'`? | D-149 |
| 6 | Bids / Proposals / Negotiation — deferred, but the flow does not exist. Own step? | D-145 |

---

## Open items carried forward

| # | Item | Severity | Where |
| --- | --- | --- | --- |
| 1 | **A pipeline that has deals cannot be edited at all** — `update()` deletes every stage before re-inserting, so the FK from `deals.deal_stage_id` blocks it. Pipelines 1 and 4 carry 2 deals each. Pre-existing (also at `HEAD`). **Needs a business rule nobody has stated:** what happens to deals on a removed or renamed stage? | **High** | D-132 |
| 2 | **Money is typed `float` in the Dashboard DTOs, against P2** (money is a `string`) | Medium | report §7.1 |
| 3 | **`lead_module_plan.md` §1 declares lead columns that do not exist.** The plan is the drifted artifact | Medium | D-116, D-148 |
| 4 | **A lead with no company name** produces a contact and a deal with no company — schema-legal, business question unanswered | Medium | `lead/15` deferred 1 |
| 5 | **`LeadConverted` carries no `assignedTo`**, so a conversion's contact and deal are unowned even when the lead has an owner | Medium | `lead/15` deferred 3 |
| 6 | **The lead index has no delete button** — `destroy` works over HTTP, nothing in the UI calls it | Medium | `lead/14` deferred 1 |
| 7 | `LeadEntity` is `final class` and mutates in place | Medium | D-060 / D-074 |
| 8 | Inline English literals in the older Lead services | Low | D-102 |
| 9 | Four orphan rows in the `migrations` table | Low | D-136 |
| 10 | `/tmp/migration-backup/` holds copies of the deleted migrations | Process | `database/17` §4.9 |
| 11 | **`DashboardStageSummaryResponseDTO` drops the stage `id`** its read model carries | Low | report §7.2 |
| 12 | The board's grouping/capping lives in the **Blade view**, not the application layer | Low | D-140 |
| 13 | **Activity due dates render in `APP_TIMEZONE`, not UTC** — `toAtom()` emits UTC, the Blade parses without a timezone | Low | D-154 |
| 14 | `DashboardFeedRepository::trackableLabel()` hardcodes the label column with an inline `instanceof` | Low | report §7.6 |
| 15 | **Both dashboard controllers inject the concrete `DashboardQueryService`**, not the contract, against AGENTS.md *INBOUND SERVICE CONTRACTS* | Medium | report §7.9 |
| 16 | Empty `00-MASTER-ROADMAP.md` | Process | this file |
| 17 | Proposed `AGENTS.md` clarifications awaiting review — **twenty-eight** from D-124 … D-154. D-130, D-134, D-141, D-144 and **D-151** (a brief's proposed class names may already exist under other names) are the most worth reading | Process | `DECISIONS.md` |

---

## No next module is defined

`docs/ai-plans/00-MASTER-ROADMAP.md` is **empty** — it exists but contains no content, so there is no
declared module order to continue from. Nothing was invented to fill it.

**Do not start a module without the developer naming it.** Per A13, the next session should ask which
module to run rather than inferring one from folder contents.

---

## Environment notes (carried forward — these cost time when rediscovered)

1. **`shell_exec('php artisan tinker')` does not work here.** Bootstrap Laravel **in-process** instead
   (`require bootstrap/app.php` + `Console\Kernel::bootstrap()`).
2. **Only the `mysql` PDO driver is available** — no `pdo_sqlite`. Feature tests needing an in-memory
   database cannot run.
3. **Database name is `lead_bridge_db`**, not `lead_bridge`. Read it from `.env`.
4. **HTTP probes need real authentication.** Use `Auth::guard('web')->login($user)` and attach
   `app('session.store')`. A token-less write is correctly refused with 419, so a write probe must mint
   a real CSRF token.
5. **`Result` exposes `errorCode` as a public property**, not a getter. Only `isSuccess()`,
   `isFailure()`, `getData()`, `getMessage()`.
6. **`composer dump-autoload` must be run** after creating Core files.
7. **Bootstrap 5.0.0 / 5.0.1** — no `text-bg-*` helpers. Pair `bg-*` with a `text-*` colour.
8. **`php artisan optimize:clear`** after touching providers or routes.
9. **Probing statuses: use the lowercase enum value.** `status = 'QUALIFIED'` makes the read adapter
   return `new`. The adapter resolves status **case-sensitively**.
10. **`->after()` is an ALTER-only modifier** and is invalid SQL in `Schema::create()`.
    `->unique()->index()` chained is the same class of trap: MySQL compiles the pair into a single
    **non-unique** index.
11. **To test migrations safely:** create a scratch database and point the app at it with
    `DB_DATABASE=... php artisan migrate:fresh --force`. `parse_ini_file()` on `.env` **fails** — read
    credentials from Laravel's `config()`.
12. **The `migrations` table has more rows than there are files.** Orphan rows are inert; do not
    "fix" this by re-adding files (D-136).
13. **Probing a Blade view: `'metrics' => null` skips the whole page.** Get real data from the query
    service first.
14. **A "dead constant" can hide a real defect.** Grep for where a constant is **consumed**, not just
    declared (D-140).
15. **Diagnosing a front-end interaction bug without a browser:** install jsdom in a scratch dir
    **outside** the repo, dump real page HTML, `new JSDOM(html, { runScripts: 'outside-only' })`,
    `window.eval(<bundle source>)`, dispatch real `MouseEvent`s. Never `npm install` into the project.
16. **Verify a bundled library by executing it or by its `VERSION` constant, never by its header
    comment** (D-141).
17. **Bootstrap 5 removed the jQuery plugin bridge.** Anything calling `$.fn.modal/tooltip/tab` **must**
    guard with `typeof $.fn.X === 'function'` or it throws and kills the rest of its
    `$(document).ready` body (D-144).
18. **A front-end probe must settle ASYNCHRONOUSLY** — Bootstrap activates panes inside a transition.
    Wait ~60 ms or you read the old state and conclude the library is broken (D-143).
19. **A jsdom probe can lie in three more ways:** (a) `npm install` in a scratch dir can silently
    **swap** an installed version — assert what you loaded; (b) scripts passed to `window.eval()` defer
    their `$(document).ready`, so a synchronous try/catch sees no throw; (c) reduce the question to a
    **straight-line primitive** before trusting any result.

---

## Rules in force for the next session

- Testing remains **PAUSED** (UNIT TESTING POLICY). Do not load `.claude/rules/07-testing.txt`, do not
  write or run tests, do not claim them.
- **Forbidden without explicit instruction:** migrations/seeders, package installs, test suites, git
  history changes, deleting files the AI did not create in that step.
- **Never edit** `AGENTS.md`, `.claude/rules/`, skill files, or the plan. Propose changes in the report
  and in `DECISIONS.md` instead.
- Play the **Interactive Alert** (sound, then toast) on every HALT.

---

## Continue prompt

> Build the Dashboard Actionable Cockpit from the design in
> `docs/ai-reports/dashboard/01-actionable-cockpit.md`. Read that report and decisions D-145 … D-154
> first, plus `docs/ai-reports/NEXT-SESSION.md` for the environment notes.
>
> **Do not create `DashboardProjectionDTO`, `DashboardResponseDTO` or `DashboardRepository`** — those
> roles already exist as `DashboardMetrics`, `DashboardOverviewResponseDTO` and the three read adapters
> (D-151). **Do not rename any existing DTO property** (P16). **Do not add a dashboard command service
> or route** — the actions call Lead's existing contracts (D-149, D-150).
>
> Before writing code, get my answers to the six questions in the "six questions blocking the build"
> table above — the stale thresholds and the "best source" definition are business rules that are not
> mine to invent.

Or the highest-severity pre-existing bug, unrelated to the dashboard:

> Fix D-132: a pipeline that has deals cannot be edited. `PipelineWriteRepository::update()` deletes
> every `deal_stages` row for the pipeline before re-inserting the incoming set, so the FK from
> `deals.deal_stage_id` blocks the delete and the whole update rolls back. Pipelines 1 and 4 are
> affected. Read `docs/ai-reports/pipeline/16-single-default-flag.md` §5 and `DECISIONS.md` D-132 first.
> **The fix needs a business rule the plan does not contain** — when an edit removes or renames a stage,
> what happens to deals sitting on it? Confirm that rule with me before building anything.

To start a different module instead, name it explicitly — there is no roadmap to continue from:

> Continue the plan for the **<module>** module. Read `docs/ai-reports/NEXT-SESSION.md` first for the
> environment notes.
