# Decisions Log

Decisions taken during execution where no rule file settled the question. Each entry
records the most conservative option that fits the architecture, plus a proposed change
to `AGENTS.md` for the developer to accept or reject.

---

## 2026-10-29 — Dashboard: Actionable Cockpit (design only, no code written)

Step: `dashboard/01-actionable-cockpit`. Report: `docs/ai-reports/dashboard/01-actionable-cockpit.md`.
**No application code was written, so nothing here is verified against a diff.** Every entry is a
design decision awaiting the developer's go-ahead.

### D-145 — The "three-round bidding" copy is NOT added: it has no data model behind it

**Situation.** The brief asked for three new keys in `resources/lang/en/leads.php` — "Bids",
"Proposals", "Negotiation". AGENTS.md's STRICT SCOPE ADHERENCE forbids acting outside the prompt and
FILE EXISTENCE GUARD makes an occupied path a stop; neither covers "the prompt names a thing that does
not exist". The developer confirmed 文案先放着,只做 Dashboard — leave the copy, do the Dashboard only.

**Finding.** None of the three has a data model, a status, or a table behind it, so a key added today
would render nowhere. Audited:

- `LeadStatus` (`core/Lead/Domain/Enums/LeadStatus.php`) declares exactly five cases — `new`,
  `contacted`, `qualified`, `converted`, `lost`.
- `LeadTransitions::ALLOWED` (`core/Lead/Domain/LeadTransitions.php`) walks them one step at a time
  and names no bidding round.
- `deals` (`database/migrations/2026_09_29_234547_create_deals_table.php`) carries a single
  `decimal('amount', 15, 2)`. There is no round, version, or revision column, and no
  bids/proposals/negotiations table among the 15 migrations.
- A case-insensitive search for `bid`, `proposal` and `negotiat` across `core/`, `app/`, `database/`,
  `resources/` and `docs/` returned **zero matches for the bidding flow**. The literal substring `bid`
  does hit 8 times as an inside-word fragment of `jobIdentifier` / `job identifier` in the Shared queue
  port and its adapter, plus one unrelated prose comment
  (`resources/views/admin/deals/index.blade.php`: "cannot remove a deal mid-negotiation"). Nothing
  matches `proposal`, and nothing matches `negotiation` as a domain concept.

**Decision (the developer's).** Do not add the keys. Recorded here so the request is not silently
dropped and so a future step does not re-add three keys whose data model still does not exist.

**What would have to exist first.** Which statuses the three rounds are, where each round's amount is
stored, who moves a lead between rounds, and what happens to a lead that fails a round.

**Proposed AGENTS.md change.** None. STRICT SCOPE ADHERENCE covers it.

### D-146 — "New Leads (Uncontacted)" is defined as `status = new`, not as "never called"

**Situation.** The brief names "New Leads (Uncontacted)" as the most urgent metric but defines neither
word. AGENTS.md forbids inventing a business rule, so the definition has to come from an existing one
or be recorded as a gap.

**Finding — neither reading is fully provable today.**

| Reading | Status | Evidence |
| --- | --- | --- |
| `status = 'new'` | **Backed** | `LeadStatus::NEW`, the `leads.status` default of `'new'`, and the transition table's entry point |
| "no completed call/meeting/email activity" | **Contradicted in the live data** | All 5 `new` leads already carry activities |

**Decision.** Define it as `status = 'new'`, and surface the activity-based reading as a separate,
honestly-named metric rather than conflating them.

**Why, beyond "the column is there".** The Activity module has no path back to `LeadStatus`. A logged
call is *not* a status transition — the Lead details page's own quick-action bar logs a call without
contacting the lead, and no listener moves the status. So "uncontacted" read as *never touched* is a
quantity the system can measure but the status machine cannot act on, and pairing it with the word
"urgent" would label a lead urgent forever with no status change able to clear it. The actionable
reading is the one whose clearing action is a real, already-implemented `LeadEntity::transitionTo()`.

**Considered and rejected.** Inferring a `CallLogged` domain event from the presence of a call
activity. That invents both a business rule and a cross-module coupling, and the evidence does not
support it: all 5 `new` leads already carry activities, so the inference would classify the entire
cohort as contacted on day one.

**Proposed AGENTS.md change.** None — this is a definition gap in the brief, not a rule gap. The
developer should confirm the definition, and it should then be written into the Dashboard module's
plan.

### D-147 — Stale-lead thresholds are proposed as literals and deliberately left unset

**Situation.** Zone B asks for "leads that […] have been in the same stage for too long (Stale
Leads)". No number exists anywhere in the project.

**Finding.** Core holds **no** duration constant of any kind — the only time constant in the whole of
`core/` is `ActivityEntity::CLOCK_SKEW_TOLERANCE_SECONDS = 60`, a clock-skew guard, not a threshold.
`DashboardRules` holds only `FEED_LIMIT = 8` and `BOARD_LIMIT = 12`. There is also no `overdue`
concept for leads: `ActivityEntity::isOverdue(DateTimeImmutable $now)` exists for *activities* only.

**Decision.** Do not pick the thresholds. The blueprint names candidate values and marks each one
**UNSET**, with the rule that an unconfigured stale panel renders as "not configured" rather than as
an empty list — an empty list would be an unverifiable claim that no lead is stale. When the developer
supplies the numbers they become named constants on `DashboardRules`, or on a Lead-owned rules class,
since the threshold is a fact about leads rather than about the dashboard.

**Proposed AGENTS.md change.** None.

### D-148 — Conversion Velocity ("New → Converted") is not computable, and the plan filed a false claim about it

**Situation.** Zone A asks for "Avg. time it takes for a lead to move from 'New' → 'Converted'".

**Finding — the endpoints are not observable.** Both are needed and neither exists:

- **"Left New" is recorded nowhere.** No `status_changed_at` column on `leads`; no lead-status-change
  history table. A lead's only timestamps are `created_at` and `updated_at`, and `updated_at` is
  written by *every* edit, so it does not record the transition. The value cannot be recovered
  retroactively, so this is **not fixable by a query and not fixable by a backfill** — it needs a new
  column and a write-path change.
- **"Reached Converted" is stored differently from the brief's expectation.** Of `LeadStatus`'s five
  cases the schema indexes **one**: `$table->string('status')->default('new')->index()`. The brief's
  "Contacted" and "Qualified" are not distinct states in the system at all; they are the same state as
  "New". "Converted" *is* observable, but through `lead_conversions.converted_at` (unique per lead,
  `useCurrent()`) rather than through a timestamp on the lead.
- **`created_at` → `converted_at` is computable but is NOT the requested metric.** It measures
  capture-to-conversion, so it includes however long the lead sat in *New* without moving. The live
  database holds exactly one conversion — created `2026-10-03 13:05:05`, converted `2026-10-12
  16:32:00`, i.e. **219 hours ≈ 9.1 days** — and that single row is almost certainly a probe row.

**Decision.** Do not compute it against the brief's wording. Either (a) report capture-to-conversion
under an honest name such as "Avg. time to convert", or (b) treat the `status_changed_at` column plus a
status-history table as its own step.

**Recorded also because it corrects a claim in the plan.**
`docs/ai-plans/lead/lead_module_plan.md` §1 declares `first_name`, `last_name`, `notes` and
`deleted_at` as lead columns. The live `leads` table has **none** of the four — it has `name`,
`company_name`, and no soft deletes. The plan is the drifted artifact, the same finding already
recorded as D-116, and this brief's status vocabulary is drifting with it. **Do not build the velocity
metric against the plan's schema.**

**Proposed AGENTS.md change.** Consider requiring that a brief naming a computed metric also names the
columns the metric reads, so an uncomputable metric is caught at design time rather than mid-build.

### D-149 — "Assign Me a New Lead" means the most urgent *unassigned* lead, not a new one

**Situation.** Zone B proposes a Quick Action button labelled "Assign Me a New Lead".

**Decision.** Interpret as **"claim an unassigned lead"**: reassigning an already-owned lead from
another agent is a different action with different semantics — a take-over, arguably an admin
privilege — and the brief's stated intent is to shorten the time from seeing work to starting it.

**Finding — "unassigned" and "new" are independent, and the brief's target cohort is not what the
label implies.** All **5** `new` leads are unassigned, but so is every other lead: **all 11 leads in
the live database are unassigned**, including 3 `converted` and 2 `contacted`. A button offering *the
most urgent unassigned lead* would therefore frequently hand over a lead that is not new.

**Decision.** The button offers the most urgent **unassigned** lead, ordered by status urgency and then
by age, and its label should say so. If the developer wants "new only", that is a filter change and
should be stated as one.

**Reuse, not new construction.** The write side already exists and does exactly this:
`LeadOwnerCommandServiceContract::assignOwner(LeadOwnerAssignDTO)` with
`(leadId, assignedTo, requestedBy)`, refusing a non-existent user and returning a
`LeadOwnerResponseDTO`. `App\Http\Controllers\Api\V1\LeadController` already exposes it. The dashboard
must **call** it, not rebuild it.

**Proposed AGENTS.md change.** None.

### D-150 — The inline status write reuses the existing `admin.leads.update` route rather than adding a dashboard route

**Situation.** Zone B's inline action needs a write endpoint. The brief's flow diagram reads
"Controller → Mapper → CommandService → Entity Behavior → Repository".

**Decision.** Do not add a route. `admin.leads.update` already reaches `LeadCommandService` →
`LeadEntity::transitionTo()` → `LeadTransitions::can()` → the lead write repository, which is exactly
the flow the brief describes. A second route to the same use case would be a second entry point to one
port, which FILE EXISTENCE GUARD and PORT METHOD NAMING both push against.

**Status: needs the developer's confirmation**, because A10 lists "any change outside the step's scope"
as a hard stop, and the wiring exception covers only "a newly created Controller action in the current
step" — this is an existing route, not a new one.

**Proposed AGENTS.md change.** None.

### D-151 — The cockpit is delivered as a `DashboardOverviewResponseDTO` field, not as a second DTO

**Situation.** The brief asks for both a `DashboardProjectionDTO` and a `DashboardResponseDTO`, while
also requiring "ONE single call to avoid multiple HTTP requests".

**Finding — a naming conflict with what exists, and a shape conflict with AGENTS.md.**

The standing inventory is:

| Layer | Existing artifact | Side |
| --- | --- | --- |
| Read model (Domain) | `DashboardMetrics`, `PipelineBoardSummary`, `ActivityFeedEntry` | read |
| Port (Domain) | `DashboardMetricsContract`, `DashboardBoardContract`, `DashboardFeedContract` | read |
| Adapter (Infra) | `DashboardMetricsRepository`, `DashboardBoardRepository`, `DashboardFeedRepository` | read |
| Application mapper | `DashboardResponseMapper` (concrete, no contract) | read |
| Response DTO | `DashboardOverviewResponseDTO` and its three children | read |

So the read model the brief calls `DashboardProjectionDTO` **already exists** as
`Core\Dashboard\Domain\DataObjects\DashboardMetrics`, and the projection-returning ports and adapters
exist with it. Per FILE EXISTENCE GUARD, creating a second file at a path already occupied is the
damaging case. Separately, AGENTS.md *(WRITE AND READ PORT PLACEMENT)* forbids a `DashboardRepository`
doing both reads and writes: Dashboard is read-only, so it has **no** `RepositoryContract` at all, only
read ports. The brief's "a specific DashboardRepository must handle the aggregations" is therefore
already satisfied three times over.

**Decision.** Add one field, `actionCenter`, to the existing `DashboardOverviewResponseDTO`. Keep the
flat `DashboardMetricsResponseDTO` untouched, so **the web path needs no controller change** and the
**API path's existing keys are untouched**. Additive only: no rename, and no new DTO for data that
already has one.

**Proposed AGENTS.md change.** None — but worth noting that "the brief's proposed class names already
exist under other names" is the same class of stale premise as D-137 and D-141, and this is its third
occurrence.

### D-152 — The action-feed store is a new *read* port, not a widening of the feed port; and the brief's `Result<DashboardResponseDTO>` is not adopted

**Situation.** The brief requires `DashboardQueryService` to return one payload covering all four
zones, and asks the controller to return `Result<DashboardResponseDTO>`.

**Decision — two parts.**

1. The urgency store is declared as its own read port, `DashboardActionFeedContract`, with its own DTO,
   and does **not** widen `DashboardFeedContract`. The precedent is already in this module and one step
   old: `DashboardBoardContract::getDefaultPipelineId()` was declared on the Dashboard's own port
   rather than widening the Deal module's contract (D-139). Like the other Dashboard read adapters it
   extends **nothing** — a read adapter that can `save()` is a boundary breach.
2. `Result<DashboardResponseDTO>` is **not** adopted as a literal signature. The project's `Result` is a
   plain `final readonly class` wrapping `mixed`, not a generic; parameterising it would change the
   envelope's contract across every module, far outside this step. The stated intent — one DTO, one
   call, the controller maps nothing — is already met by the existing `getOverview()` returning a
   `Result` around `DashboardOverviewResponseDTO`.

**Proposed AGENTS.md change.** None.

### D-153 — "Best performing source" is left undefined, because the two defensible readings disagree

**Situation.** Zone D asks which lead source has the highest conversion rate.

**Finding.** `leads.source` is **free text with no foreign key and no enum**. The live database holds
**seven distinct values across 11 rows**, and five of them are prose rather than source names:

| Source | Leads |
| --- | --- |
| `landing_page` | 3 |
| `postman` | 3 |
| `probe` | 1 |
| `website` | 1 |
| a 63-character Latin lorem-ipsum sentence | 1 |
| a 77-character Latin lorem-ipsum sentence | 1 |
| a 97-character Latin lorem-ipsum sentence | 1 |

Any of the three singleton lorem rows wins outright on raw percentage, so **a naive `MAX` would report
a made-up sentence as the best channel.** The two defensible readings also point at different
features: highest conversion **rate**, which needs a minimum-volume guard, versus highest conversion
**count**, which needs none.

**Decision.** Define nothing. Ask which question is meant, and whether a minimum sample size should
gate a rate-based answer. Recorded rather than guessed, because guessing produces a number that looks
authoritative and is wrong — the worst possible output for a dashboard.

**Proposed AGENTS.md change.** None. `/admin/leads` exposes a free-text `source` filter and nothing in
the codebase states whether `source` is a lookup key or a free-form attribution string; until that is
stated, `source` is not a usable grouping key.

### D-154 — All 13 pending activities are overdue, so the urgency panel needs a lookback window

**Situation.** Zone B replaces "Upcoming Activities" with "Overdue/Urgent".

**Finding.** Measured on the live database:

| Bucket | Count |
| --- | --- |
| Overdue — `due_date < now()`, not completed | **13** |
| Upcoming — `due_date >= now()`, not completed | **0** |
| Pending with no due date | 0 |
| Completed | 1 |

`DashboardFeedRepository::getUpcomingActivities()` filters `completed_at IS NULL` **and**
`due_date IS NOT NULL`, then orders `due_date ASC`. Today's "Upcoming activities" panel is therefore
already showing the 13 **overdue** rows, oldest first — it is the urgency list under a misleading name.
Renaming it changes the label but not the content, and at 8 rows (`FEED_LIMIT`) it silently truncates 5
of them behind "view all".

**Decision.** The cockpit splits the panel into *Overdue* (ascending by due date, so the oldest debt
leads) and *Due soon* (ascending), and the blueprint carries **the same "unset threshold" treatment as
D-147** for how far back an overdue row is still worth showing. Note also that the feed repository
skips activities with no due date entirely — 0 rows today, but that is why its count must not be
assumed to match `metrics.pendingActivities`.

**Date handling.** `DashboardResponseMapper::toAtom()` emits UTC ISO-8601, and the Blade calls
`Carbon::parse()` with **no explicit timezone**, so it currently renders in `APP_TIMEZONE` rather than
UTC. Overdue styling must compute against **UTC**; the `due_date` mapping itself should be addressed as
its own change rather than silently inside this step.

**Proposed AGENTS.md change.** None.

---

## D-001 — Module bindings live in a `<Module>ServiceProvider`, not `AppServiceProvider`

- **Date:** 2026-10-20
- **Step:** Deal 01 (`docs/ai-plans/deal/01-api-store.md`)
- **Context:** The step brief's ACCEPTANCE line says "contract bindings are registered in
  `AppServiceProvider`". However, `AGENTS.md` also states the hard-stop/wiring exception
  covers touching `app/Providers/AppServiceProvider.php` for a newly created Contract,
  and every module delivered so far uses its own provider: `ContactServiceProvider`,
  `PipelineServiceProvider`, `CompanyServiceProvider`, `LeadServiceProvider`,
  `ActivityServiceProvider`. `AppServiceProvider` now holds only Shared-kernel ports and
  the morph map.
- **Decision:** Created `app/Providers/DealServiceProvider.php` and registered it in
  `bootstrap/providers.php`, matching the established per-module pattern.
- **Reasoning:** A per-module provider keeps `AppServiceProvider` from accumulating every
  module's bindings and matches the pattern a reviewer will compare against. Registering in
  `bootstrap/providers.php` is the sanctioned wiring exception, so no hard stop was needed.
- **Proposed AGENTS.md change:** Make the provider placement explicit — add to the
  `app/Providers` responsibility description: *"Each module's ports are bound in its own
  `<Module>ServiceProvider`, registered in `bootstrap/providers.php`. `AppServiceProvider`
  holds only Shared-kernel ports and framework-wide configuration."*

---

## D-002 — Money travels as a decimal string, never a float

- **Date:** 2026-10-20
- **Step:** Deal 01
- **Context:** `deals.amount` is `decimal(15,2)`. The original controller passed a raw
  request value. No rule file specifies how money is typed in the entity or DTO.
- **Decision:** `amount` is a `string` throughout (DTO, entity, response DTO, persistence
  array). `DealMapper::toAmount()` normalises with `number_format((float) $value, 2, '.', '')`.
  `DealRules::MIN_AMOUNT`/`MAX_AMOUNT` are string constants mirroring the column.
- **Reasoning:** A float round-trip on a `decimal(15,2)` column can lose precision. Keeping
  the decimal string intact matches what the column stores and what `KanbanDealResponseDTO`
  already does (`string $amount`), so the module stays consistent with the Pipeline read side.
- **Proposed AGENTS.md change:** Add a money-typing rule to the Rich Domain mandate: *"Decimal
  money columns are carried as strings (never float) in DTOs, entities, and response DTOs.
  Formatting to 2dp happens once, in the Request Mapper."*

---

## D-003 — No update/stage-transition behaviour added to `DealEntity` in Step 01

- **Date:** 2026-10-20
- **Step:** Deal 01
- **Context:** The original `DealController@update` contains a close-date stamping rule for
  Won (5) / Lost (6) stages. It was tempting to move it into the entity now, since the
  entity exists.
- **Decision:** Did **not** add stage-transition or close-date behaviour. The entity ships
  only the create-path invariants.
- **Reasoning:** Step 01's OUT OF SCOPE explicitly excludes `update`, and Step 03 owns that
  rule. Adding it now would pre-empt Step 03 and would also require choosing how stage IDs
  are modelled (magic ints 5/6) — a decision the brief did not make. Per
  `09-autonomous-execution.txt` A2.2, complex logic is deferred rather than invented.
- **Proposed AGENTS.md change:** None. This is ordinary scope discipline.

---

## D-004 — `DealEntity` ships without a `$domainEvents` list

- **Date:** 2026-10-20
- **Step:** Deal 01
- **Context:** The scaffolding rule (`03-module-scaffolding.txt`, ENTITY section) says an
  entity "also ships a readonly list of Domain Events ... and `releaseEvents()`". But
  Step 01's create use case raises no event, and `08-events-and-queues.txt` E1 states events
  are optional per module and YAGNI applies.
- **Decision:** Omitted `$domainEvents` and `releaseEvents()` from `DealEntity`.
- **Reasoning:** A private list that nothing writes and nothing reads is dead code, and
  `releaseEvents()` would always return `[]`. The list is added together with the first
  behaviour method that raises an event, at which point every behaviour method must pass it
  forward (E3.2). The entity is `readonly` and immutable, so adding the parameter later is a
  constructor change confined to the entity and its mapper.
- **Proposed AGENTS.md change:** Clarify the ENTITY mandatory-members note in
  `03-module-scaffolding.txt`: *"the `$domainEvents` list and `releaseEvents()` are added
  together with the first behaviour method that raises an event, not at scaffold time."*

---

## D-005 — Deal reads sit behind their own `DealQueryContract`, not `DealRepositoryContract`

- **Date:** 2026-10-20
- **Step:** Deal 02 (`docs/ai-plans/deal/02-api-index-show.md`)
- **Context:** Step 01 left `DealRepositoryContract` write-only (`save`). Step 02 needed
  two reads. `Contact` and `Pipeline` both declare a separate `...QueryContract` and let
  one Eloquent class implement both; the alternative was widening the existing repository
  port with `getFilteredDeals()` and `findDetailById()`.
- **Decision:** Added `core/Deal/Domain/Contracts/DealQueryContract.php` and made
  `DealEloquentRepository` implement it alongside `DealRepositoryContract`.
- **Reasoning:** CQS at the port level is what stops a Query Service from injecting a port
  that can write. One Eloquent class behind two interfaces keeps the adapter count at one
  per aggregate while the dependency each Service sees stays minimal.
- **Proposed AGENTS.md change:** Make the split explicit in the PORTS AND ADAPTERS
  mandate: *"Write and read ports are declared separately (`...RepositoryContract` and
  `...QueryContract`). One adapter class MAY implement both, but a Query Service MUST
  depend only on the query port."*

---

## D-006 — The Won/Lost close-date rule is expressed in stage names, not stage IDs

- **Date:** 2026-10-20
- **Step:** Deal 03 (`docs/ai-plans/deal/03-api-update-destroy.md`)
- **Context:** The original `DealController@update` stamped a close date when
  `deal_stage_id` was `5` or `6`. The brief said to preserve "stamping close dates for
  Won/Lost stages" and left the mechanism open. `deal_stages` has no "closes the deal"
  column, so the schema carries no flag to read.
- **Decision:** Moved the rule into `DealEntity::moveToStage()` and matched the stage's
  **name** against `DealRules::WON_STAGE_NAME` ("Closed Won") and `LOST_STAGE_NAME`
  ("Closed Lost"), case-insensitively — the same comparison `PipelineStage::hasSameName()`
  already makes, for the same reason.
- **Reasoning:** The magic IDs do not hold. Stage 5 is "Closed Won" in pipeline 1 and
  "Closing" in pipelines 2, 3 and 4, because each pipeline seeds its own stages and the
  IDs are assigned in insert order. The existing row 1 already carries
  `deal_stage_id = 6` in a *different* pipeline's numbering. A name match works for the
  default pipeline, for stage 9 in pipeline 2, and for any pipeline added later, which the
  ID list did not. Names are also what an admin edits in the UI, so the rule reads the way
  the operator thinks about it.
- **Consequence recorded:** reopening a deal now **clears** its close date. The original
  left `actual_close_date` in place, reporting a deal as closed while it sat back on the
  board. A stored date is still kept when the deal was already closed, so re-saving a won
  deal does not move the day it was won.
- **Proposed AGENTS.md change:** None needed for the rule itself, but a note in the Rich
  Domain mandate would help: *"Where a business rule maps to a seeded lookup row rather
  than a column, prefer matching the row's stable natural key over its surrogate ID, and
  record the choice in DECISIONS.md."*

---

## D-007 — A foreign key on a deal cannot be blanked by an update

- **Date:** 2026-10-20
- **Step:** Deal 03
- **Context:** The original update rules declared `company_id` and `contact_id` as
  `nullable`, so `{"company_id": null}` passed validation and `Deal::update()` nulled the
  link. `pipeline_id` and `deal_stage_id` are `NOT NULL` at the column, but `required`
  sat behind `sometimes`, so a payload could still send an empty one.
- **Decision:** The mapper rejects a **submitted blank** foreign key as bad input. An
  **absent** key leaves the stored value alone. `UpdateDealRequest` marks the submitted
  ones `required`.
- **Reasoning:** No foreign key on a deal may be emptied — a deal always carries a
  pipeline and a stage. Company and contact are links a client removes by omission, not
  by clearing. The three-state model `UpdateContactDTO` uses (absent / clear / replace)
  does not apply here, because there is no field on a deal that can be cleared.
- **Proposed AGENTS.md change:** None. This is the CRUD-pipeline rules applied literally.

---

## D-008 — `actual_close_date` is a derived field and is refused from update clients

- **Date:** 2026-10-20
- **Step:** Deal 03
- **Context:** The original update rules accepted an `actual_close_date` from the client
  and let it override the stamp the Won/Lost rule had just applied.
- **Decision:** `UpdateDealRequest` marks it `prohibited`. The stage decides it.
- **Reasoning:** `actual_close_date` is not stored independently — it is a function of the
  stage the deal sits in (D-006). Accepting it lets a client report a deal as closed while
  it sits in Discovery. A `prohibited` rule refuses the payload instead of silently
  discarding the value, which is what `09-autonomous-execution.txt` A2.2's "mirror the
  constraints" spirit asks for: report rather than guess.
- **Known gap:** `StoreDealRequest` still declares it `nullable|date`, so a create can set
  it. Flagged in the Step 03 report; `store` was outside that step's OUT OF SCOPE.
- **Proposed AGENTS.md change:** Add to the Rich Domain mandate: *"A field whose value is
  derived from a state transition is not accepted from delivery input; the transition
  method decides it, and the delivery layer refuses a client-supplied one."*

---

## D-009 — The deal list resolves its labels from the form-options read

- **Date:** 2026-10-20
- **Step:** Deal 04 / 05
- **Context:** `DealQueryContract::getFilteredDeals()` returns flat `DealResponseDTO`s
  carrying only foreign-key ids, because hydrating four relations per row is what the read
  side is meant to avoid. A list showing `company_id = 2` is not a usable page, so the
  labels have to come from somewhere.
- **Decision:** `Web\Backend\DealController::index()` also calls
  `DealQueryService::getFormOptions()` and the view resolves company, contact, pipeline and
  stage labels against those option rows (`DealOptionResponseDTO` already carries `id` and
  `label`). The list therefore costs one extra four-table read per page.
- **Reasoning:** The alternatives were worse at this size. Hydrating relations on every
  list row makes the read side do exactly the work it exists to skip. A hand-written join
  on the list query duplicates four tables' worth of label logic inside the Deal module and
  couples the Deal read model to the Company, Contact and Pipeline schemas. Reusing the
  options read adds no new coupling: the page already needs those rows, and the port
  already exists.
- **Trade-off:** The list does one extra read it would not need if the rows carried names.
  Rejected for now rather than solved, because the scale-up path is a schema-level decision
  (a `StoredDealData` that carries its own label columns, or a purpose-built joined read
  model) that belongs to the developer, not to a views step.
- **Proposed AGENTS.md change:** None. Worth noting in
  `.claude/rules/04-crud-pipeline.txt` as a known pattern for list pages whose rows come
  from a flat read model.

---

## D-010 — An optional link is cleared with a flag, not with null

- **Date:** 2026-10-20
- **Step:** Deal 05
- **Context:** D-007 recorded that no foreign key on a deal may be blanked, on the grounds
  that company and contact "are links a client removes by omission, not by clearing". That
  holds for the API, where a client that does not mention a field does not mean to change
  it. It does not hold for a web form, which posts **every** field it renders. An emptied
  company or contact picker arrives as `''`, and the mapper rejected it — so every web
  update failed with `Field [contact_id] cannot be cleared, it needs a value.` and saved
  nothing.
- **Decision:** Superseding D-007 for these two fields. `UpdateDealDTO` carries
  `unlinkCompany` / `unlinkContact` booleans, set by the mapper when the key was **present
  and blank**, and `DealEntity::applyChanges()` applies them. A link is now cleared with an
  explicit flag rather than by a null. `pipeline_id` and `deal_stage_id` remain
  unclearable, and a blanked title, amount or currency is still refused.
- **Reasoning:** The three states an update field can be in — absent (leave alone), blank
  (clear), valued (replace) — cannot be carried by a nullable `?int`, because `null`
  already means "leave alone". The flag restores the state the form was expressing without
  changing what the API means by an omitted key. Verified both ways: an API update that
  omits `company_id` keeps the stored link, and the browser form's blank picker drops it.
- **Supersedes:** The D-007 sentence *"Company and contact are links a client removes by
  omission, not by clearing."* The rest of D-007 stands.
- **Proposed AGENTS.md change:** Add to the Rich Domain mandate: *"When an update must
  distinguish 'leave unchanged' from 'clear', a nullable value cannot carry both. Carry an
  explicit flag (or a three-state value object) for the field that can be cleared, and keep
  null meaning 'unchanged'."* `UpdateContactDTO` already models the three states; this
  records the rule that made it necessary.


---

## D-011 — The dashboard reports its money totals under one currency

- **Date:** 2026-10-20
- **Step:** Dashboard 01
- **Context:** The dashboard's headline figures (`won_revenue`, `open_pipeline_value`) are
  `SUM(amount)` across every deal in the table. `deals.currency` exists and defaults to
  `BDT`, and nothing constrains every row to the same value. The original
  `Api\V1\DashboardController@metrics` hardcoded `'currency' => 'BDT'` beside those sums.
  Every current row is `BDT`, so the figures happen to be right.
- **Decision:** Kept the original behaviour and made it explicit:
  `DashboardResponseMapper::REPORTING_CURRENCY = 'BDT'`, with a docblock stating that the
  totals are a sum across pipelines and are only meaningful under one currency. The
  per-deal `currency` is not consulted.
- **Reasoning:** `AGENTS.md` A2.1 says to mirror the existing constraints rather than invent
  business rules, and no rule states a reporting currency or a conversion policy. The option
  that fits the architecture is a per-currency breakdown, which changes the shape of the
  response DTO and the page — a read-model decision for the developer, not something to
  invent mid-refactor. Flagged rather than silently kept.
- **Trade-off:** A single USD deal would be added to a BDT total and labelled `BDT`.
- **Proposed AGENTS.md change:** Add to the Response DTO mandate: *"An aggregate that sums a
  decimal column across rows of mixed currency must either group by currency or state the
  single currency it reports in. A bare `SUM` under a hardcoded currency label is not
  acceptable once more than one currency can exist."*

---

## D-012 — The dashboard's read ports live in the Dashboard module, not the modules they read

- **Date:** 2026-10-20
- **Step:** Dashboard 01
- **Context:** The dashboard's reads span four tables owned by four other modules
  (`deals`/`deal_stages` via Deal and Pipeline, `leads` via Lead, `activities` via Activity).
  Neither `DealQueryContract` nor `ActivityQueryContract` can answer "count every deal by
  the state its stage puts it in" — those ports are shaped around their own module's list
  and detail pages. `04-crud-pipeline.txt`'s rule that query services return ResponseDTOs was
  also in tension with a cross-module read.
- **Decision:** Declared three new ports **inside** `Core\Dashboard\Domain\Contracts` —
  `DashboardMetricsContract`, `DashboardBoardContract`, `DashboardFeedContract` — and
  implemented them in `Core\Dashboard\Infrastructure\Repositories`. The Dashboard module
  reaches no other module's repository or port; its adapters read the tables directly.
- **Reasoning:** A reporting module owns its own read models. Widening `DealQueryContract`
  with dashboard-shaped aggregates would put a consumer's questions into the producer's port
  and make every future module that wants a different cut widen it again. The Activity and
  Deal modules already use this shape for their own cross-table reads
  (`ActivityQueryContract` lives in Application for the same reason), so the pattern is
  established. The cost is that the Dashboard adapters know four tables' columns, which is
  exactly what an infrastructure adapter is for.
- **`04-crud-pipeline.txt` note:** The rule reads "Query Services MUST return ResponseDTOs".
  `getOverview()` returns one. The three single-panel methods also return ResponseDTOs, so
  no exception was taken — but the port returns Domain read models, which is the same split
  Deal and Activity already use.
- **Proposed AGENTS.md change:** Add to the Ports and Adapters mandate: *"A module that
  reports on tables owned by other modules declares its own read ports and adapters in its
  own module. It must not widen another module's port with its own questions, and it must
  not import another module's repository."*

---

## D-013 — The web profile controller reads the current user's id from the session

- **Date:** 2026-10-20
- **Step:** Auth 01
- **Context:** `AuthService::getProfile()` needs a user id. Behind the API, that id comes
  from the Sanctum-authenticated request. Behind the web guard there is no equivalent: the
  session holds a Laravel session key, resolved by `Auth::user()` / `$request->user()` into
  an `App\Models\User`. Core has no port for "the identity behind the current request", and
  `getProfile()` must not learn about sessions — that is a delivery concern.
- **Decision:** The web controller resolves the id in the adapter
  (`(int) $request->user()->id`), passes it through `AuthMapperContract::mapToProfileDTO()`,
  and hands the resulting `ProfileRequestDTO` to the service. The Eloquent model is used
  only as an id source and nothing else is read off it.
- **Reasoning:** The session is the web delivery mechanism's own state, and a port that
  exposed it to Core would exist purely so a WordPress build could implement the same thing
  with `wp_get_current_user()` — which is fine, but nothing in the current step needs Core
  to ask the question. Reading the id in the controller keeps the dependency arrow pointing
  inward (adapter -> Core) and mirrors how the API controller already works. Inventing a
  `CurrentUserContract` port here would be scaffolding for a hypothetical, and
  `AGENTS.md` A2.1 says to mirror what exists rather than build ahead.
- **Trade-off:** The two delivery channels each resolve identity their own way. If a console
  command or a WordPress adapter later needs the same read, a `CurrentUserContract` port
  becomes worthwhile — at that point there will be three callers and the shape will be
  obvious.
- **Proposed AGENTS.md change:** None. This fits the existing "Presentation adapters
  parse input" rule. Recorded so the next step does not mistake it for an oversight.

---

## D-014 — The User read side gets its own response mapper and DTO

- **Date:** 2026-10-20
- **Step:** User 01
- **Context:** The User module already had a write side whose `UserResponseMapper::toResponseDTO()`
  takes a `UserEntity`, and whose `UserResponseDTO` carries `id, name, email, image, role, status`
  with no timestamp. The new listing needs `created_at` (a listing column) and works from a read
  model (`StoredUserData`), not an aggregate. Two options existed: widen the write-side mapper and
  DTO to accept both shapes, or give the read side its own pair.
- **Decision:** The read side owns `UserListResponseMapper` and `UserListItemResponseDTO`. The
  write-side `UserResponseMapper` and `UserResponseDTO` are untouched.
- **Reasoning:** Widening a shared mapper means every future change to either path is a change to
  a type both paths depend on. The read and write shapes genuinely differ — one has a timestamp
  and no credential, the other is built from a validated aggregate — so a shared type would carry
  optional fields that are never populated on one side. Duplicating a seven-field list is the
  cheaper cost, and it keeps the two sides independently changeable.
- **Trade-off:** Two DTOs and two mappers now describe the same table. A field added to the `users`
  table may need adding in two places. Accepted deliberately.
- **Proposed AGENTS.md change:** None required, but worth stating explicitly: when a module has
  both a write and a read side, each MAY own its own response mapper rather than sharing one. The
  current `RESPONSE DTO MANDATE` does not say whether sharing is expected.

---

## D-015 — The read port is named `UserQueryContract`, not `UserRepositoryContract`

- **Date:** 2026-10-20
- **Step:** User 01
- **Context:** `AGENTS.md` requires repository ports to be named `<Module>RepositoryContract`. It
  also permits query services a "dedicated read-side Repository/Finder port" without fixing a
  name. The User module already had a `UserRepositoryContract` on the write side, so a second
  port called the same thing was not available.
- **Decision:** The read port is `Core\User\Domain\Contracts\UserQueryContract`, exposing
  `getFilteredUsers(UserFilter): PaginatedResult` and `findUserById(int): ?StoredUserData`. It
  returns read models, never entities. `UserRepositoryContract` remains the write port and returns
  `UserEntity`.
- **Reasoning:** The two ports return different types on purpose — an entity for writing, a plain
  read model for display — so they are not two implementations of one contract and should not
  share a name. Keeping the write contract byte-identical was also a hard requirement after the
  overwrite incident.
- **Trade-off:** `UserQueryContract` next to `UserRepositoryContract` does not tell a reader which
  is the read side without opening it. If the module grows a third port, a clearer convention
  (`...ReadContract` / `...WriteContract`) would be worth adopting.
- **Proposed AGENTS.md change:** In the `PORTS AND ADAPTERS MANDATE`, name the read-side port
  convention explicitly, e.g. `<Module>QueryContract`, so the next module does not have to decide
  this again.

---

## D-016 — Never write a file without first confirming it does not already exist

- **Date:** 2026-10-20
- **Step:** User 01
- **Context:** Building the User read side, I wrote four files that already existed and were
  tracked (`UserMapperContract.php`, `UserResponseDTO.php`, `UserMapper.php`,
  `UserResponseMapper.php`), destroying them. They were restored from `HEAD` and recovery was
  verified with `git diff --stat`. The step's real content was unaffected, but the incident cost a
  restore cycle and could have silently changed the write path.
- **Decision:** Before writing any file, confirm whether it exists and whether it is tracked
  (`git ls-files <path>` or a directory listing). A read side added to a module that already has a
  write side is the specific case that caused this: the module is not new just because the part
  being built is.
- **Reasoning:** The failure was not a reasoning error about architecture — the architecture was
  right — it was a missing check on the filesystem. That check is cheap and the failure mode is
  expensive and silent.
- **Trade-off:** None. This only adds a lookup before a write.
- **Proposed AGENTS.md change:** Add a line under `STRICT SCOPE ADHERENCE`: before creating a file,
  verify it does not already exist; if it does, treat modifying it as out of scope and halt unless
  the prompt names that file.

---

## D-017 — Domain entities stay `readonly class`, not `final class`

- **Date:** 2026-10-21
- **Step:** User 02
- **Context:** `AGENTS.md` says `final` MUST be used for implementation classes "where applicable".
  Rules 03 and 05 template entities as `readonly class`, and all seven existing entities in the
  project (`ContactEntity`, `LeadEntity`, and the rest) are `readonly class`. The two instructions
  point in opposite directions for entities.
- **Decision:** Entities are `readonly class`. The `final` requirement is read as applying to
  Services, Mappers, Adapters and other implementation classes, not to entities.
- **Reasoning:** Two reasons, one mechanical and one practical. Mechanically, `readonly class` is
  already an immutability guarantee that is strictly stronger than `final` for the thing the
  mandate actually cares about — a `final` entity can still be mutated if its properties are not
  readonly. Practically, following the seven existing entities keeps the module uniform, and a
  single entity breaking the house style is worse for a reader than a rule being applied twice.
- **Trade-off:** An entity can be extended, so a subclass could add a behaviour method. In practice
  nothing in the project subclasses an entity, and the Readonly guarantee still stops any subclass
  from mutating state.
- **Proposed AGENTS.md change:** In `RICH DOMAIN MODEL MANDATE`, state that entities are declared
  `readonly class` and that the `final` requirement applies to services, mappers and adapters.

---

## D-018 — The status transition matrix was read off the admin view

- **Date:** 2026-10-21
- **Step:** User 02
- **Context:** The entity had to enforce which account status may move to which, and nothing in the
  project states the rule. The only statement of intent is
  `resources/views/admin/users/index.blade.php`, which renders an Approve button when the status is
  not APPROVED and a Suspend button when it is, with a corresponding
  `@if ($user->status === 'APPROVED')` guard.
- **Decision:** `changeStatusTo()` allows three moves: a not-yet-approved account to APPROVED, an
  APPROVED account to SUSPENDED, and a SUSPENDED account back to APPROVED. Approving an approved
  account is refused (`UserAlreadyApprovedException`); suspending an account that is not approved is
  refused (`UserAlreadyActiveException`). Moving to PENDING is passed through, because no screen
  offers it and refusing it would encoding a rule nobody asked for.
- **Reasoning:** The UI is the only place the business expressed an opinion about status moves, and
  a rule derived from the screen the business actually uses is better evidence than one I invent.
  Making it explicit on the entity also means the rule survives the web controller being written
  later — the controller cannot get it wrong.
- **Trade-off:** If the business intends a different matrix, the entity is now the one place to
  change it, which is the point. But the intent was inferred, not stated, so it should be confirmed.
- **Proposed AGENTS.md change:** None. This is module business knowledge. It is recorded here
  because the source of the rule is a Blade template, which is a fragile place for a business rule
  to live.

---

## D-019 — The write port passes Entities, and `UserData` was retired

- **Date:** 2026-10-21
- **Step:** User 02
- **Context:** `UserRepositoryContract` took and returned `UserData`, an Application-layer data bag.
  `AGENTS.md` states "DTOs or loose arrays MUST NOT be passed from the Service to the Repository"
  and "Repositories take and return Entities". Migrating the port was therefore required by the
  mandate, but it changes an interface the write path depends on.
- **Decision:** `create`, `update` and `updateStatus` take and return `UserEntity`, and a new
  `findByIdForUpdate` was added. `UserData` had no reference anywhere else in the codebase and was
  deleted. `UserEntityMapper::toPersistenceArray()` was added as the flattening counterpart to
  `toEntity()`.
- **Reasoning:** Without this, the entity's new behaviour methods would have had no caller —
  `register()`, `approve()` and `applyChanges()` would exist but nothing would use them, which is a
  rich model in name only. The mandate and the prompt both point the same way here.
- **Trade-off:** The port change is a breaking interface change, and it is what broke the paused
  `UserServiceTest`. Entities are also heavier to build than a data bag, so every read on the write
  side now runs the entity's three constructor invariants.
- **Proposed AGENTS.md change:** None. The mandate already says this; the decision is recorded so a
  later reader knows the port change was deliberate rather than drift.

---

## D-020 — Enum hydration uses `from()`, not `tryFrom() ?? default`

- **Date:** 2026-10-21
- **Step:** User 02
- **Context:** `UserEntityMapper::toEntity()` read `AuthRole::tryFrom((string) $data['role']) ??
  AuthRole::MEMBER` and did the same for status with PENDING. A role or status the domain does not
  recognise therefore became MEMBER/PENDING silently.
- **Decision:** Both use `from()`. A value the enum does not know now throws instead of being
  rewritten.
- **Reasoning:** A role string that is not a valid role is corrupt storage, not a user who happens
  to be a member. Silently rewriting it turns a detectable data problem into a silent privilege
  change — and for `role` specifically, quietly demoting an ADMIN to MEMBER, or the reverse, is a
  security-relevant outcome. `AGENTS.md` also forbids silent failures.
- **Trade-off:** A single corrupt row now makes the listing it appears in throw rather than render.
  The alternative is worse. `AuthStatus` and `AuthRole` are both backed enums whose every legal
  value comes from a database column this application writes.
- **Proposed AGENTS.md change:** None. Covered by `ERROR HANDLING AND DEFENSIVE PROGRAMMING`.

---

## D-021 — No Domain Events are raised yet, by design

- **Date:** 2026-10-21
- **Step:** User 02
- **Context:** `AGENTS.md` requires every entity to carry a `domainEvents` list and for behaviour
  methods to pass it to the new instance, which `UserEntity` does. It does not require an entity to
  raise any event. Rule 08's SCOPE NOTE says events are optional and that YAGNI applies.
- **Decision:** `UserEntity` carries the list and exposes `releaseEvents()`, but no behaviour method
  appends an event. `UserStatusChangedEvent` and `UserCreatedEvent` were considered and rejected:
  a grep found no listener for either, and adding them would have forced `EventDispatcherContract`
  into `UserService`'s constructor for a dispatch nobody receives.
- **Reasoning:** An event with no subscriber is dead code that still has to be maintained and can
  still be got wrong (a lost event list is a silent bug). Rule 08 explicitly permits waiting.
  Adding the dispatcher would also have broken the paused `UserServiceTest` a second time for no
  behaviour gain.
- **Trade-off:** When a listener is eventually wanted — an approval email is the likely first —
  the entity will need editing to append the event. That is a small, local change, and the
  mechanical structure is already in place for it.
- **Proposed AGENTS.md change:** None. Rule 08 already covers this.

## D-022 — Write-path `updated_at` is the pre-write value, and `saveStatus()` stays `void`

- **Date:** 2026-10-23
- **Step:** Lead — Service Layer DTO Boundary
- **Context:** Enforcing the Response DTO mandate on `LeadService::createLead()` and
  `changeStatus()` meant those methods had to produce a `LeadResponseDTO`. `createLead()`
  is served correctly because `LeadRepositoryContract::create()` returns the persisted
  `LeadEntity` rebuilt from the row, so its timestamps are true. `changeStatus()` is not:
  `LeadRepositoryContract::saveStatus()` returns `void`, so the entity in hand was loaded
  *before* the write and its `updatedAt` is the value the row carried beforehand.
  The sibling reference module does better — `UserRepositoryContract::updateStatus()`
  returns `UserEntity`, and `UserService::updateUserStatus()` maps the returned entity.
- **Decision:** `saveStatus()` keeps its `void` return and `changeStatus()` maps the
  pre-write entity. The stale `updated_at` is documented in a code comment at the call
  site. Changing `saveStatus()` to return the saved `LeadEntity` was ruled out of scope.
- **Reasoning:** `LeadRepositoryContract` is a cross-module port with consumers outside
  the Lead module. Widening its return type was not part of the authorised scope
  ("fix both write methods"), and it would have broken `LeadConversionServiceTest`'s
  mock expectation on `saveStatus`. The conservative option that fits the architecture
  is to satisfy the DTO mandate now and record the weaker timestamp as a known trade-off,
  since the mandated contract is about the *shape* of the return, and the shape is now
  correct.
- **Trade-off:** A `PATCH /leads/{id}/status` response advertises an `updated_at` that
  is one write behind. A client that needs the true value must follow up with
  `GET /leads/{id}`. Every other field, including `status`, is correct.
- **Proposed AGENTS.md change:** None directly. This is a module-level port detail, not
  a rule. If the developer wants the reference shape enforced repository-wide, the
  candidate addition belongs in `.claude/rules/05-rich-domain-and-services.txt` §R8:
  *"A repository write method that mutates an existing Entity returns the persisted
  Entity, so the Service can map it without a second read."* That would make
  `saveStatus()` a known deviation rather than an accepted one. Awaiting developer
  approval — no rule file was edited.

## D-023 — `saveStatus()` returns the stored Entity; D-022 is retired

- **Date:** 2026-10-23
- **Step:** Lead — DTO boundary scope expansion (item 1)
- **Context:** D-022 accepted a stale `updated_at` on the write path because widening
  `LeadRepositoryContract::saveStatus()` was out of the authorised scope. The developer
  then authorised exactly that change, and `UserRepositoryContract::updateStatus()` already
  returns `UserEntity`, so the Lead port was the deviation from the reference shape rather
  than the norm.
- **Decision:** `saveStatus(LeadEntity): LeadEntity` now returns the stored row rebuilt from
  the database, and `LeadService::changeStatus()` maps that instead of the entity it loaded.
  `LeadConversionService::convert()` was updated to consume the new return value.
  A row that vanishes between the load and the write now throws `RuntimeException` rather
  than reporting a save that never happened.
- **Reasoning:** It removes the trade-off D-022 recorded, closes the gap with the reference
  module, and needs no extra query — `updateRecord()` already returned the saved model, so
  the only change was to map and return it.
- **Trade-off:** Two acknowledged costs, both recorded rather than hidden.
  (1) The port returns `final class LeadEntity`, and PHPUnit cannot double a final class to
  invent a return value, so every mock of this method must now supply one explicitly via
  `willReturnCallback`. Two test classes needed that.
  (2) `updateRecord()` returns the model WITHOUT refreshing it, so a DB-side `ON UPDATE`
  trigger would not be reflected. This matches every sibling module; `->refresh()` would be
  one extra query for a case no module has. **Documented in full as D-024.**
- **Proposed AGENTS.md change:** **APPLIED.** The developer explicitly authorised editing
  `.claude/rules/`, and the line proposed in D-022 is now in
  `.claude/rules/05-rich-domain-and-services.txt` §R8: *"A repository write method that
  mutates an existing Entity RETURNS the persisted Entity (e.g. updateStatus(Entity):
  Entity), so the Service maps the stored values without a second read."* The next module
  therefore follows this shape by default. See the final clean-up report.
- **Supersedes:** D-022.

## D-024 — `saveStatus()` intentionally omits `->refresh()`; DB-side triggers are not observed

- **Date:** 2026-10-23
- **Step:** Lead — final clean-up phase (item 3)
- **Context:** `LeadRepository::saveStatus()` completes the required write contract from
  D-023 by returning `$this->mapper->toEntity($leadModel->getAttributes())`, where
  `$leadModel` is what `BaseRepository::updateRecord()` returned. `updateRecord()` fills
  and saves the model but does NOT call `->refresh()`, so the returned attributes are the
  values Eloquent wrote plus anything the database changed that Eloquent is aware of.
  A column mutated by a database-side mechanism — a `BEFORE/AFTER UPDATE` trigger, or a
  `DEFAULT ... ON UPDATE CURRENT_TIMESTAMP` clause Eloquent does not know about — would not
  appear in the mapped Entity.
- **Decision:** `saveStatus()` deliberately does NOT call `$leadModel->refresh()`.
  This is a conscious, documented limitation, not an oversight.
- **Reasoning:** (1) Consistency: every sibling module behaves identically —
  `UserRepository::updateStatus()`, `CompanyService`, `DealService`, and `PipelineService`
  all map the model the write returned without refreshing. Adding a refresh to Lead alone
  would make it the one module whose write path costs an extra `SELECT`, and the next
  module author would have no rule telling them which shape to copy. (2) Cost: `refresh()`
  is an additional round trip on every status change, paid on every request to insure
  against a mechanism this schema does not currently use. (3) Testability: the paused
  test suite mocks `LeadRepositoryContract`, so a refresh inside the adapter is invisible
  to the Application-layer tests either way.
- **Trade-off:** If a database trigger is ever added to the `leads` table that rewrites a
  column on update, the `LeadResponseDTO` returned by `POST /leads/{id}/status` will report
  the pre-trigger value for that column while the row itself holds the post-trigger one —
  a client would have to `GET /leads/{id}` to see it. The `updated_at` the API reports is
  correct today because Eloquent sets that timestamp itself, so it is in the attributes the
  write returned; the limitation only bites for values the database mutates on its own.
- **Revisit condition:** If this repo ever introduces DB-side triggers or generated
  columns on an Entity's table, `->refresh()` becomes required and this decision must be
  superseded. The Natural fix at that point is one line in the adapter:
  `$leadModel->refresh();` before mapping.
- **Proposed AGENTS.md change:** None. This is a deliberate module-level trade-off, and
  D-023's §R8 rule already fixes the contract shape that matters. Recording the limitation
  here is what the developer asked for; it is not proposed as a project-wide rule.

---

## D-025 — The `QueryException` 23000 catch sits outside the transaction callback

- **Context:** The step asked to wrap `LeadService::createLead()` in
  `$this->transactionManager->run()` and to catch `QueryException` code `23000` "inside the
  callback", returning a domain failure so a queued duplicate is rejected gracefully rather
  than crashing into `failed_jobs`.
- **Decision:** The `run()` call is wrapped in an outer `try`. The `catch (QueryException)`
  that maps code `23000` to `LEAD_ALREADY_EXISTS` sits **outside** the callback, in
  `createLead()`'s own catch block. The callback keeps only the lookup, the mapping, and the
  insert.
- **Reasoning:** (1) The callback *is* the transaction body. `DB::transaction()` commits
  when the callback returns normally — including when it returns a `Result::failure()`.
  Catching the duplicate inside the callback and returning a value would therefore **COMMIT**
  a transaction whose statement had failed. Letting the exception escape makes the adapter
  roll back, which is the correct outcome for a failed insert. (2) The behaviour the step
  actually needed — a graceful `LEAD_ALREADY_EXISTS` domain failure instead of an unhandled
  exception — is identical from the caller's side, and was verified by running two
  concurrently-gated processes against the real constraint.
- **Trade-off:** The callback's responsibility is now "perform the write", and duplicate
  interpretation is a concern of the method that owns the use case. Anyone reading the
  callback alone will not see the 23000 handling; the method DocBlock states where it lives.
- **Revisit condition:** If a future use case needs to *continue* after a duplicate (for
  example, upsert-then-return-existing), the callback would need restructuring so the
  duplicate does not abort the transaction at all — a different shape from this one.
- **Proposed AGENTS.md change:** Worth stating the general rule, since the prompt's instinct
  (catch inside) is a natural mistake: *a `QueryException` that must become a domain failure
  is caught around the transaction boundary, never inside the callback, because returning a
  failure from inside the callback commits.*

---

## D-026 — `Illuminate\Database\QueryException` is imported into an Application service

- **Context:** Turning a duplicate-key rejection into a domain failure requires recognising
  the vendor exception. `LeadService` lives in `core/Lead/Application/Services/`, and
  AGENTS.md forbids `Illuminate\*` imports in Core Domain **and** Application files. The
  portability checklist explicitly requires that no framework import exists in either layer.
- **Decision:** The import was made anyway, on the developer's explicit instruction, to
  satisfy the step's requirement that a `23000` be distinguishable from other write errors.
- **Reasoning:** There is no alternative that satisfies the step without either a new port
  method (out of scope) or inspecting `$exception->errorInfo`, which is equally
  framework-shaped. Distinguishing `23000` from, say, a lost connection cannot be done by
  catching `\Throwable` alone.
- **Trade-off:** This is a **real, violated architectural constraint**, not a stylistic
  choice. `core/` is no longer framework-portable at this one point: porting to WordPress
  would require editing this Application file, which the PRIMARY GOAL section says must never
  be necessary. Any reviewer running the portability checklist against this file will —
  correctly — fail it.
- **Revisit condition:** Supersede this decision as soon as a `DuplicateKeyException` (or a
  `PersistenceFailureContract` exposing an `isDuplicateKey(): bool`) exists in
  `core/Shared/Domain/Contracts/`. At that point the adapter catches `QueryException` and
  translates it, the Core catches only the port's own exception, and this import is deleted.
- **Proposed AGENTS.md change:** None needed — AGENTS.md is already correct here. This
  decision records a known, deliberate violation so it is not mistaken for an oversight, and
  so the port-level fix has a home. It should be treated as technical debt with the
  `Revisit condition` above as the trigger.
