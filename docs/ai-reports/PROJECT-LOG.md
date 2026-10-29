# Project Log

Running log of executed steps. Newest entry first.

---

## 2026-10-29 — Dashboard: Actionable Cockpit, Step 01 (design only)

- **Status:** DONE_WITH_NOTES. **No application code was written.** Design deliverables produced;
  four premises in the brief did not hold.
- **Report:** `docs/ai-reports/dashboard/01-actionable-cockpit.md`
- **Decisions:** D-145 … D-154

### What this step produced

The four requested deliverables — layout blueprint, DTO definition, service signature, mapping
strategy — as a design against the **existing** Dashboard module, which turned out to already satisfy
most of the brief's architectural mandates. `DashboardQueryService` has always been read-only, the
read ports and adapters are already split correctly, and `getOverview()` already returns the whole page
in one call.

The blueprint promotes Zone B (Action Center) **above** the four vanity tiles, because the current page
opens with `Won revenue` / `Open pipeline` / `Win rate` / `Pending activities` while **13 overdue
activities sit below the fold**. It adds an `actionCenter` field to `DashboardOverviewResponseDTO` and
one method, `getActionCenter()`, to the query contract. No existing DTO property is renamed, so
`/api/v1/dashboard` and the Blade page keep every key they have today.

Every new action calls an **existing** Lead command contract. No dashboard command service and no new
dashboard route.

### Four brief premises that did not hold

| Premise | Reality |
| --- | --- |
| Add "Bids / Proposals / Negotiation" to `leads.php` | No data model — five lead statuses, no round/version column, zero grep hits. **Deferred by the developer.** (D-145) |
| Conversion Velocity = New → Converted | **Not computable.** No `status_changed_at`, no history table; and the schema indexes exactly one status value, so the brief's "Contacted"/"Qualified" are not stored states. (D-148) |
| Create `DashboardProjectionDTO` / `DashboardResponseDTO` / `DashboardRepository` | All three roles **already exist** as `DashboardMetrics`, `DashboardOverviewResponseDTO` and the three read adapters. Creating them would be the duplicate-file violation. (D-151) |
| "Best performing source" | `leads.source` is free text with 7 values across 11 rows, **5 of them prose** — a naive `MAX` reports a lorem-ipsum sentence as the best channel. Left undefined deliberately. (D-153) |

The third is the **third** occurrence of the same class of stale premise (after D-137 and D-141) and
is proposed as an AGENTS.md line.

### Measured, not estimated

A bootstrapped probe against the live `lead_bridge_db` produced every number in the report: leads by
status `{new:5, contacted:2, qualified:1, converted:3, lost:0}`, **11/11 unassigned**, 7 distinct
sources, activities `{overdue:13, upcoming:0, done:1}`, 1 conversion at **219 h**, 1 currency.

Two of those measurements changed the design: **all 5 `new` leads already carry activities** (so
"uncontacted" cannot mean "untouched", D-146), and **all 13 pending activities are overdue and zero are
upcoming** (so today's "Upcoming activities" panel is already the overdue list under a misleading name,
D-154).

### Eight defects found and deliberately not fixed

Recorded in §7 of the report. The highest-value: **P2 violation** — `DashboardMetricsResponseDTO`
declares money as `float` where AGENTS.md mandates `string`; and **`lead_module_plan.md` §1 declares
lead columns that do not exist** (`first_name`, `last_name`, `notes`, `deleted_at`), the same drift
already filed as D-116.

### Why this halted rather than built

AGENTS.md STRICT SCOPE ADHERENCE forbids acting outside the prompt and FILE EXISTENCE GUARD makes an
occupied path a stop, but neither covers *"the prompt names a thing that does not exist"*. Two entries
needed a business rule nobody has stated (the stale thresholds, and which "best source" means), and one
— the bidding copy — was put to the developer, who chose to defer it.

---

## 2026-10-21 — User module, Step 02: UserEntity as a Rich Domain Model

- **Status:** COMPLETE, verified by execution. Two deviations carried forward.
- **Report:** `docs/ai-reports/user/02-user-entity-rich-domain.md`

### What was built

`UserEntity` was an anemic data bag: 12 public properties, no behaviour, no invariants. Every rule
the account has lived in `UserService`. It is now a `readonly` entity with named constructors
(`register`, `reconstitute`), constructor-enforced invariants (usable name, valid email, hashed
password), and behaviour methods that return new instances — `approve()`, `suspend()`,
`changeStatusTo()`, `applyChanges()`, `assertCanSignIn()`, `passwordMatches()`.

Because rules are only authoritative when they are the only path, the write side moved onto the
entity in the same step: `UserRepositoryContract` now takes and returns `UserEntity` instead of the
Application-layer `UserData` carrier (which was deleted), `UserEntityMapper` reconstitutes and
flattens, and `UserService` is now a Command Service that decides nothing about account state.

11 files changed, 5 created, 1 deleted.

### Verification

26 container/database assertions and 7 HTTP assertions, all passing. `php -l` clean on every
changed file; `composer dump-autoload` succeeded.

The load-bearing checks: `Repository create keeps the password hash` (the model's `#[Hidden]`
password column would have been silently wiped by `toArray()`) and `Updated account still signs in
with its old password` (proves the edit path preserves credentials and status).

### Carried forward — not fixed

- `tests/Unit/User/UserServiceTest.php` no longer runs: the `mapper:` parameter was renamed to
  `responseMapper:`, and its fake `'hashed-password'` is now rejected by the entity's password
  guard. Testing is PAUSED, so the test was neither edited nor run.
- `app/Http/Requests/Api/V1/UpdateUserRequest.php` carries a comment about the mapper's MEMBER and
  PENDING fallback that no longer exists. Presentation file, outside the named scope, left alone.

### Follow-ups this step surfaced

The web write path for users does not exist at all (no `Web\Backend\UserController`, no
`admin.users` routes) even though `resources/views/admin/users/` has three Blade templates and the
index view renders Approve/Suspend buttons. Nothing authorises the API user routes beyond
`auth:sanctum`, so any signed-in account can edit any other including promoting itself to ADMIN.
The Auth module writes the same `users` table through a second, still-anemic path.

---

## 2026-10-20 — User module, Step 01: API index and show

- **Status:** COMPLETE (after an incident — see below)
- **Report:** `docs/ai-reports/user/01-user-api-index-show.md`

### What was built

A read side for the User module, added beside the pre-existing write side and
sharing no surface with it: `UserRules`, `InvalidUserException`, `UserFilter`,
`StoredUserData`, `UserQueryContract`, `UserFilterDTO`, `UserListItemResponseDTO`,
`UserListFilterMapper`, `UserListResponseMapper`, `UserQueryService`, and
`UserQueryRepository`. Wired through `UserServiceProvider`, a rewritten
`Api\V1\UserController`, `IndexUserRequest`, and a new
`GET /api/v1/users/{user}` route.

11 files created, 4 modified. Nothing pre-existing in `core/User` was changed.

### Incident — four tracked files overwritten, then restored

While building the read side I assumed I was creating the module and wrote
`UserMapperContract.php`, `UserResponseDTO.php`, `UserMapper.php` and
`UserResponseMapper.php` over pre-existing tracked files. The developer supplied a
`git checkout --` restore command (two of its paths were wrong; I confirmed the
real paths with `git ls-files` first). Recovery verified: `git diff --stat
core/User/` is empty, so every tracked file is byte-identical to `HEAD`.

Root cause: I did not check whether the files already existed before writing them.

### Why the read side duplicates a mapper

The write side's `UserResponseMapper` takes a `UserEntity` and its DTO has no
`created_at`. A listing needs a read model and a timestamp, so the read side got
its own mapper and DTO rather than widening the write-side contract that the write
path depends on. Cost is one duplicated field list; benefit is that neither side
can break the other.

### Verification

- `php -l` clean on all 12 touched PHP files; `composer dump-autoload` exit 0
  (7174 classes).
- Real bootstrap + real database: container resolves the read chain; default page
  size 15; `per_page` of `abc`/`0`/`101` rejected and `1`/`100` accepted; list
  returns total 2 with typed DTOs; no `password` key in the DTO's JSON;
  `created_at` ISO-8601; detail found and missing; `READ_COLUMNS` excludes
  `password` at the SQL level.
- Real server on port 8112 with a real Sanctum token: 401 without a token; 200
  with `meta.total = 2`; row hides `password` and `email_verified_at`; detail 200;
  missing id 404 `USER_NOT_FOUND`; bad page sizes 422 `USER_INVALID`.
- Cleanup: scratch token and three scratch scripts deleted, port 8112 server
  stopped.

### Carried forward, not fixed

- **`UserService` is unresolvable.** `UserRepositoryContract` is bound nowhere;
  `HEAD` has no User bindings in `bootstrap/providers.php` or `AppServiceProvider`,
  so the write path was never wired up before this step. Out of scope here.
- **Two adjacent contract names.** `UserRepositoryContract` (write) versus
  `UserQueryContract` (read) — worth a naming decision if the module grows.

---

## 2026-10-20 — Auth module, Step 01: Web Routes and Profile

- **Status:** COMPLETE
- **Report:** `docs/ai-reports/auth/01-web-auth-and-profile.md`

### What was built

The profile read is now reachable from both delivery channels. Added
`ProfileRequestDTO`, `AuthMapperContract::mapToProfileDTO()`, and a web `me()`
action behind `GET /profile` with its own Blade page. `AuthService::getProfile()`
takes the DTO instead of a bare `int`.

2 files created, 8 modified.

### What the survey actually found

The web Auth controller already existed and already worked — login, register and
logout were complete and passing, so no web controller was created. The real gaps
were the missing profile route/view, an API `me()` that ignored its `Result`
entirely, and profile links in the sidebar and header pointing at `#` and a dead
`profile.html`.

### Bugs found and fixed

- **`Api\V1\AuthController::me()` never checked `isFailure()`.** It returned
  `getData()` under a hardcoded `'status' => 'success'`, so a failed profile read
  answered `{"status":"success","data":null}` with HTTP 200. Now returns 404 with
  the service's message.

### Verification

- `php -l` clean on all 7 changed PHP files; `composer dump-autoload` exit 0
  (7161 classes).
- `route:list` shows `profile` under the `auth` middleware; a guest hit is 302 to
  `/login`.
- Real HTTP with a real session: admin login -> `/profile` 200, 143,077 bytes,
  every figure tying to user #7's row, no stack trace, no unrendered Blade.
- Full journey with a throwaway member: register -> login -> profile (200, badge
  `user #45`, `MEMBER`/`APPROVED`) -> logout -> profile 302. The row was removed
  afterwards.
- Failure branch driven directly: unknown id -> `isFailure=true`,
  `AUTH_USER_NOT_FOUND`, data `NULL`.
- Profile links render twice on every page (sidebar + header dropdown), and the
  sidebar item carries `active-page` on `/profile` and not on `/dashboard`.
- API regression: login, bad-credential 422 and register validation all unchanged.
- Portability grep: no framework import in any Auth Domain or Application file.
- Scratch token deleted (11 pre-existing untouched); users back to 2 rows; seeded
  admin password re-checked; port 8110 server stopped.

### Decisions recorded

- **D-013** The web profile controller resolves the session user's id in the
  adapter; Core has no session-identity port yet.

See `docs/ai-reports/DECISIONS.md`.

### Open items for the developer

- **The API `me()` response gained a `message` key it did not have before.** The
  change is additive and aligns `me()` with `login()` and `register()`, but a
  client doing a strict shape comparison would notice.
- Profile editing was not built — the prompt asked for the read, and no update use
  case exists in `AuthService` yet.
- `RegisterRequest` validates an `image` field the register form never renders, and
  the form is `multipart/form-data` for it. Pre-existing, left untouched.
- D-013 and the earlier open decisions propose `AGENTS.md` clarifications and await
  approval.

---

## 2026-10-20 — Dashboard module, Step 01: Fat Controller Refactor

- **Status:** COMPLETE
- **Report:** `docs/ai-reports/dashboard/01-dashboard-module.md`

### What was built

Both dashboard controllers moved off inline Eloquent onto the canonical read
flow: mapper -> `DashboardQueryService` -> `Result` -> view/JSON. Introduced the
`core/Dashboard` module (Domain, Application, Infrastructure) with three read
ports and three adapters, its composition root `DashboardServiceProvider`, and
`GET v1/dashboard` alongside the existing `v1/dashboard/metrics`.

23 files created, 4 modified.

### Verification

- `php -l` clean on all 29 touched files; `composer dump-autoload` exit 0.
- All five container bindings resolve through the real container.
- Three routes registered in `route:list`.
- Real DB: every headline figure cross-checked against the raw rows — deals
  total=4 won=1 lost=1 open=2, wonRevenue=180000, openValue=3500.5, winRate=50.
- Case-insensitive stage matching round-tripped (stage 5 renamed to `closed won`
  and restored) with identical figures.
- Real HTTP: no token -> 401; with token -> 200 with `metrics`, `pipeline` (14
  rows) and `upcoming_activities` (8 rows). `v1/dashboard/metrics` returns the
  byte-identical body and message the original controller did.
- Scratch token deleted; both dev servers stopped; no PHP processes left. Row
  counts unchanged at `deals=4 leads=7 stages=14 activities=14`. The seeded admin
  password was overwritten during HTTP verification and restored + re-checked.
- Portability grep: no framework import in any Domain or Application file.

### Bugs found and fixed during the step

- **Won/lost were matched by stage id (5/6), which is only correct for the
  default pipeline.** Id 5 is `Closed Won` in the default pipeline and `Closing`
  in every other one. Now matched by name through `DealBoardRules`, delegating to
  the same rule `DealEntity::closesTheDeal()` applies, so reporting and the write
  side cannot disagree.
- **The feed reported no label for any deal.** Leads/contacts/companies carry
  `name`; deals carry `title`. `trackableLabel()` now picks the column per model.
- **The web dashboard page 500'd** — `view('admin.dashboard', $user)` passed a
  `User` model where Blade expects an array.

### The web page

`resources/views/admin/dashboard.blade.php` rebuilt (302 lines) on the same design
system as the other pages: four headline tiles, a per-stage table with proportional
bars, the activity feed, a lead-conversion panel, and two summary badge rows. Every
list has a specific empty state; the failure branch renders an error and a fallback
panel without leaking the exception.

Verified over real HTTP with a real session: guest -> 302 to /login; login -> 302 to
/dashboard; authed -> **200 OK**, 189,366 bytes, all six panels and every figure
present, 15 progress bars, no stack trace, no leftover Blade directives. The page
read back as text ties to the underlying rows.

### Decisions recorded

- **D-011** Money totals are summed across currencies under one reported currency.
- **D-012** The dashboard declares its own read ports rather than widening Deal's
  or Activity's.

See `docs/ai-reports/DECISIONS.md`.

### Open items for the developer

- D-011 and D-012 propose `AGENTS.md` clarifications; D-001, D-002, D-005, D-007,
  D-009 and D-010 from the Deal module still await approval.
- The dashboard page was verified as served HTML (real session, real server), not
  visually in a browser — no screenshot or layout check was done.
- No dashboard entry was added to the sidebar — it already links to the route.

---

## 2026-10-20 — Deal module, Step 05: Web Views and Navigation

- **Status:** COMPLETE
- **Report:** `docs/ai-reports/deal/05-web-views.md`
- **Plan:** `docs/ai-plans/deal/05-web-views.md`

### What was built

The four Deal Blade pages (`index`, `create`, `edit`, `show`), a shared form partial, the
stage picker's pipeline narrowing, and the Deals branch in all three sidebar partials.
`master.blade.php` gained `@stack('scripts')` so page scripts run after the bundled ones.

5 files created, 6 modified (3 of them in `core/Deal`, see below).

### Two real defects found by running the write path

1. **Every web update was silently rejected.** The web form posts an empty picker when the
   user links no contact, but the shared mapper refused a blank `contact_id` as a client
   error — correct for the API, wrong for a form that posts every field. Since `null`
   already meant "leave the stored value alone", no null could express "unlink", so
   `UpdateDealDTO` gained `unlinkCompany` / `unlinkContact` flags and the entity applies
   them. The API behaves exactly as before; the regression check confirms it.
2. **A blanked `amount` or `currency` was silently accepted on update** while the form
   request validated them `required`. Now rejected, matching title, pipeline and stage.

### Verification

- `php -l` clean on every file changed.
- All four pages through the HTTP kernel, authenticated, real DB: 200 with no error markup.
- Real seeded values render: titles, `250000.00 BDT`, the `2026-10-13` closed badge,
  company/contact/pipeline/stage labels.
- Hostile input renders a page: `pipeline_id=999` -> empty state, `per_page=abc` -> warning
  and a list, `999999` -> redirect.
- Write path through the controller: create, update onto a closing stage (stamps today),
  reopen (clears), blank picker (unlinks), blank title (rejected), delete.
- Mapper rules driven directly for both the API's absent-key rule and the form's
  blank-means-unlink rule; all five required fields reject a blank.
- Deal API regression over HTTP: create, list, detail, partial update, closing stage,
  404, delete, 401 — all unchanged.
- Portability grep: no framework import in any Domain or Application file.

All scratch rows deleted; `deals` back to ids `[1,2,3,4]`; all scratch tokens revoked.

---

## 2026-10-20 — Deal module, Step 04: Web Controller

- **Status:** COMPLETE
- **Report:** `docs/ai-reports/deal/04-web-controller.md`
- **Plan:** `docs/ai-plans/deal/04-web-controller.md`

### What was built

`Web\Backend\DealController` with the seven resource actions, three web form requests, the
`DealFormOptionsContract` binding, and the `admin.deals` resource route. Every action is
thin: input DTO through the mapper, one service call, `Result` branch, view or redirect.

4 files created, 2 modified.

### Notable choices

- The list read is tolerant of a bad filter: the mapper is stricter than the validator, so
  a rejected value is dropped and warned about rather than failing the page.
- The list filter and both forms need rows from other modules' tables, so they consume
  `DealQueryService::getFormOptions()` rather than reaching for Eloquent from delivery.
- `update()` reads the target stage's **name** before the mapper runs, so the close-date
  rule stays in `moveToStage()` instead of leaking a Won/Lost comparison into delivery.

### Verification

- `php -l` clean; `route:list --path=admin/deals` shows all 7 routes.
- Controller, `DealFormOptionsContract` -> `DealFormOptionsRepository` resolve.
- `getFormOptions()` returns pipelines=4, stages=14, companies=6, contacts=4.
- Write path through the controller: create, update, delete. Scratch row cleaned up.

---

## 2026-10-20 — Deal module, Step 03: API Update and Destroy

- **Status:** COMPLETE
- **Report:** `docs/ai-reports/deal/03-api-update-destroy.md`
- **Plan:** `docs/ai-plans/deal/03-api-update-destroy.md`

### What was built

`DealController@update` and `destroy` moved off inline Eloquent and `DB::transaction`
onto the canonical write flow: FormRequest -> `DealMapperContract::mapToUpdateDTO()` ->
`DealService::updateDeal()`/`deleteDeal()` -> `Result` -> JSON. The close-date rule became
`DealEntity::moveToStage()`, and every direct `Eloquent`/`DB` call left the controller.
All 5 API endpoints are now complete.

1 file created, 9 modified. The whole `deals` API works end to end.

### The close-date rule, re-derived

The original hard-coded stage IDs 5 and 6. Seeding shows why that does not hold: stage 5
is "Closed Won" in pipeline 1 and "Closing" in pipelines 2-4. The rule now matches the
stage **name** (`DealRules::WON_STAGE_NAME` / `LOST_STAGE_NAME`), which is what the
`deal_stages` schema actually encodes. Reopening a deal now clears its close date instead
of leaving it reported as closed while it sits on the board.

### Verification

- `php -l` clean on all 26 files; `composer dump-autoload` exit 0 (7127 classes).
- All six container bindings resolve; all 5 routes registered.
- Entity rules driven directly: stamp on Won/Lost, keep a stored date, clear on reopen,
  case-insensitive name match, guards on stage ID and date form.
- Mapper driven directly: partial payloads, absent-vs-blank keys, page-size and ID
  rejection.
- HTTP kernel against the real DB: create -> update to Won -> re-save (date steady) ->
  reopen (date cleared) -> partial title update; `actual_close_date` -> 422; blank FK ->
  422; bad FK -> 422; missing -> 404; delete -> 200; delete again -> 404.
- Index and show re-verified: filters, pagination meta, nested relations, 404 path.
- Portability grep: no framework import in any Domain or Application file.

Scratch deal, tokens and scripts removed; `deals` is back to ids `[1,2,3,4]`.

### Open item flagged for the developer

`StoreDealRequest` still accepts `actual_close_date` from clients — the same
inconsistency `UpdateDealRequest` now refuses. `store` is outside Step 03's OUT OF SCOPE,
so it was left alone; the fix is to mark it `prohibited` there too.

### Decisions recorded

- **D-006** The Won/Lost close-date rule is expressed in stage names, not stage IDs.
- **D-007** A foreign key on a deal cannot be blanked by an update; absent means unchanged.
- **D-008** `actual_close_date` is a derived field and is refused from update clients.

See `docs/ai-reports/DECISIONS.md`.

---

## 2026-10-20 — Deal module, Step 02: API Index and Show

- **Status:** COMPLETE
- **Report:** `docs/ai-reports/deal/02-api-index-show.md`
- **Plan:** `docs/ai-plans/deal/02-api-index-show.md`

### What was built

`DealController@index` and `show` moved off inline Eloquent onto the canonical read flow:
FormRequest -> `DealMapperContract::mapToFilterDTO()` -> `DealQueryService` -> `Result` ->
JSON. Added the read-side port `DealQueryContract`, `DealFilter` value object,
`StoredDealData`, `DealFilterDTO`, `DealDetailResponseDTO` and five nested relation DTOs.

10 files created, 7 modified. `update` and `destroy` were left untouched, per the step's
OUT OF SCOPE.

### Verification

- `php -l` clean on every touched file; `composer dump-autoload` exit 0.
- `DealQueryContract` and `DealQueryService` resolve through the real container.
- `deals.index` and `deals.show` registered in `route:list`.
- Real DB + HTTP kernel: unfiltered list total=4; `pipeline_id=4` -> 2 rows;
  `deal_stage_id=6` -> 1 row; both filters -> 0; `per_page=999` and `pipeline_id=abc`
  -> 422 `DEAL_INVALID`; `show` carries company, contact, pipeline and stage names;
  missing id -> 404 `DEAL_NOT_FOUND`.
- Row count unchanged at 4 — the read paths wrote nothing.
- Portability grep: no framework import in any Domain or Application file.

### Decisions recorded

- **D-005** Reads sit behind their own `DealQueryContract` rather than widening
  `DealRepositoryContract`.

See `docs/ai-reports/DECISIONS.md`.

### Open items for the developer

- D-001, D-002 and D-005 propose small `AGENTS.md` clarifications; all await approval.
- Steps 03-05 of the Deal plan remain.

---

## 2026-10-20 — Deal module, Step 01: API Store

- **Status:** COMPLETE
- **Report:** `docs/ai-reports/deal/01-api-store.md`
- **Plan:** `docs/ai-plans/deal/01-api-store.md`

### What was built

`DealController@store` refactored from an inline-validation, inline-Eloquent method into the
canonical flow: FormRequest -> `DealMapperContract` -> `DealService::createDeal()` -> `Result`
-> JSON response. Introduced the `core/Deal` module (Domain, Application, Infrastructure) and
its composition root `DealServiceProvider`.

12 files created, 2 modified. `index`, `show`, `update`, and `destroy` were left untouched,
per the step's OUT OF SCOPE.

### Verification

- `php -l` clean on all 16 touched files.
- `composer dump-autoload` exit 0.
- All four container bindings resolve to their concrete classes.
- `route:list` shows `deals.store` registered.
- Happy path against the real DB: title trimmed, amount normalised to `1500.00`, currency
  upper-cased to `BDT`, ISO-8601 timestamps.
- Failure paths: blank/long title, bad currency, and non-positive IDs -> `DEAL_INVALID`;
  FK violation -> `DEAL_CREATE_FAILED`; no partial writes in any case.
- HTTP: valid create -> 201, validation failures -> 422, no token -> 401 over real TCP.
- Portability grep: no framework import in any Domain or Application file.

All test rows, tokens, and scratch files were cleaned up; `deals` is back to its original
4 rows.

### Decisions recorded

- **D-001** Bindings went into a new `DealServiceProvider` (repo pattern) rather than
  `AppServiceProvider` as the brief's acceptance line said.
- **D-002** Money is carried as a decimal string, never a float.
- **D-003** No stage-transition/close-date behaviour added — Step 03 owns it.
- **D-004** `DealEntity` ships without `$domainEvents` — nothing raises an event yet.

See `docs/ai-reports/DECISIONS.md`.

### Open items for the developer

- D-001 and D-002 propose small `AGENTS.md` clarifications; both await approval.
- Steps 02-05 of the Deal plan remain.

---

## 2026-10-23 — Lead: Service Layer DTO boundary

**Step:** Enforce the AGENTS.md RESPONSE DTO MANDATE on the two `LeadService`
write methods that still returned a Domain Entity.

Enforcement pass, not a feature. `getLeads()` and `getLeadById()` were already
compliant; `createLead()` and `changeStatus()` returned a bare `LeadEntity`,
putting a domain object on the wire at `POST /leads` and forcing
`Api\V1\LeadController::updateStatus()` to issue a redundant second read just to
get a presentable shape.

Note on the brief: the policy as worded banned "raw Eloquent Models, Collections,
or generic arrays", which would not have caught this. What leaked was a pure
Domain Entity — the AGENTS.md clause is the one that applies.

- **Touched:** `LeadResponseMapper` (new `toResponseDTOFromEntity()` entry point
  sharing one private `build()` with the read path), `LeadService` (both write
  methods now map), `LeadServiceInterface` (docblocks), `Api\V1\LeadController`
  (redundant re-read removed).
- **Verified:** `php -l` clean; all 4 `Result::success` payloads audited by reading
  the lines; live tinker check confirmed read and write paths emit byte-identical
  JSON including ISO-8601 timestamps.
- **Not done:** no tests written or run (testing paused); pre-existing stale
  `LeadConversionServiceTest` / `LeadStatusChangeTest` left alone.
- **Decision:** D-022 — `saveStatus()` stays `void`, so write-path `updated_at` is
  the pre-write value. Documented at the call site.

Report: `docs/ai-reports/lead/STEP-lead-dto-boundary.md`

---

## 2026-10-23 — Lead: DTO boundary scope expansion

**Items:** (1) execute D-022, (2) repair the two named test classes, (3) enforce
rule 02 §7 in `LeadRepository`.

**1 — `saveStatus()` returns `LeadEntity`.** The port and adapter now return the stored
row and `changeStatus()` maps it, so `updated_at` reflects the write. Proven against the
real DB: `2026-10-05 10:13:16` -> `2026-10-23 09:46:47`. `LeadConversionService` updated
to consume the new return type. A vanished row now throws instead of faking a save.
Retires D-022.

**2 — the brief's stated cause was wrong.** `LeadEntity::$status` is not a promoted
parameter (reflection: `promoted=false`), and named-argument construction succeeds —
so there were no `status:` named args to remove. The actual six failures were: a stub for
`TransactionManagerContract::run()`, a method that does not exist on the port; PHPUnit
being unable to double `final LeadEntity` for `saveStatus()`; and `expects()` overriding
the stubbed return value. Fixed the real causes. Added a test that fails if the D-022 fix
regresses. Also repaired `ApiLeadStatusUpdateTest`, left broken by the previous step's
removal of the controller read-back.

**3 — rule 02 §7.** Six `$leadModel->toArray()` -> `getAttributes()`. Verified the mapper
handles raw strings, since the `Lead` model declares no `$casts`. `LeadData::toArray()`
is a domain object and correctly untouched.

**Verified:** `php -l` clean on 9 files; Lead suites 133 passed / 319 assertions;
full suite 451 passed with 5 pre-existing `UserServiceTest` errors (proven pre-existing
by stashing and re-running on the clean tree).

Report: `docs/ai-reports/lead/STEP-lead-dto-boundary-expansion.md`
Decision: D-023 (supersedes D-022)

---

## 2026-10-23 — Lead: final clean-up phase

**Items:** (1) repair `UserServiceTest`, (2) apply the D-023 rule to `.claude/rules/` §R8,
(3) document the `->refresh()` omission.

**1 — `UserServiceTest` repaired (5 errors -> 5 passed).** The named-argument mismatch was
only the first layer: the constructor parameter is `$responseMapper`, but the test passed
`mapper:`. Fixing that surfaced a second, deeper cause the errors had been masking — the
fixture used `password: 'hashed-password'`, and `UserEntity::assertPasswordIsHashed()`
rejects any value `password_get_info()` does not recognise as a real algorithm. Replaced
with a genuine bcrypt digest in a named constant. Not a defect in production code.

**2 — D-023 enforced in `.claude/rules/05-rich-domain-and-services.txt` §R8.** Added the
rule that a write method mutating an existing Entity returns the persisted Entity, with the
reasoning (a `void` return forces the Service to map stale data or issue a redundant read).
This is the one place `.claude/rules/` was edited, and only because the prompt asked for it.
D-023's "awaiting approval" note updated to APPLIED.

**3 — DB trigger limitation documented as D-024.** `saveStatus()` deliberately omits
`->refresh()`, matching every sibling module. Records the trade-off (a DB-side trigger or
generated column would not be reflected), the reasoning, and the revisit condition.

**Final suite status: 456 tests, 456 passed, 1218 assertions, 0 failures, 0 errors.**

Report: `docs/ai-reports/lead/STEP-lead-final-cleanup.md`
Decision: D-024 (D-023 marked applied)

---

## 2026-10-23 — Lead: transaction boundary + duplicate-insert guard

**Prompt:** Implement `LaravelTransactionManager::run()` via `DB::transaction($callback)`,
wrap `LeadService::createLead()` in it, and map a `QueryException` code `23000` to
`Result::failure(..., 'LEAD_ALREADY_EXISTS')` so queued duplicate webhooks are rejected
gracefully instead of landing in `failed_jobs`.

**Halted first.** The prompt's premise was that a UNIQUE index on `leads.email`/`phone`
exists. It did not. The create migration chains `->unique()->nullable()->index()`, which
compiles to a **single non-unique index**, confirmed both in `SHOW CREATE TABLE` and by a
duplicate insert succeeding. Two further blockers: `LeadService` had no transaction manager
in its constructor, and `DB::transaction()` changes `run()`'s nesting behaviour versus the
hand-rolled version. All three were reported before any code was written; the developer
authorized all three, ordered the broken tests left alone, and authorized the migration.

**1 — Migration.** New `2026_10_23_120000_add_unique_indexes_to_leads_table.php` drops the
non-unique indexes and adds named UNIQUE ones (`leads_email_unique`, `leads_phone_unique`),
with a reversing `down()`. Checked the data first: 7 rows, no duplicates, no nulls, so the
constraint applied cleanly. Applied with `php artisan migrate --force` (authorized).

**2 — Adapter.** `run()` is now `return DB::transaction($callback);`. Verified nesting: a
level-1 outer transaction makes `run()` a savepoint (level 2), and the outer rollback unwinds
to 0 — the behaviour the hand-rolled version could not provide.

**3 — Service.** `TransactionManagerContract` added as the 5th promoted constructor
parameter; `createLead()` body wrapped in `run()`; `catch (QueryException)` maps `23000` to
`LEAD_ALREADY_EXISTS` and re-throws nothing else as a duplicate. Success path still returns
`toResponseDTOFromEntity()`. `createLead()`'s own signature is unchanged.

**Deviation (D-025).** The 23000 catch sits *outside* the callback, not inside it as the
prompt worded it. Returning a `Result` from inside the callback would make `DB::transaction()`
**commit** a transaction whose insert had failed. Behaviour from the caller's side is
identical, and was proven so.

**Verified by execution, not assertion:**
- duplicate rejected through the Service under two concurrently-gated processes —
  `A: success=true / B: success=false code='LEAD_ALREADY_EXISTS'`
- raw SQLSTATE is the string `'23000'`, matching the constant comparison
- `failed_jobs` = 0 after the duplicate runs
- `createLead()` reflection shows one parameter and a `Result` return
- probe rows deleted; `leads` restored to its original 7 rows

**Debt (D-026).** `Illuminate\Database\QueryException` is now imported into an Application
service, which violates the portability checklist in AGENTS.md. Made on explicit
instruction; recorded as a real violation with a concrete revisit condition (a
`DuplicateKeyException` in `core/Shared/Domain/Contracts/`).

**Not done, as ordered:** tests were neither run nor updated.
`tests/Unit/Lead/LeadStatusChangeTest.php:65` and `tests/Unit/Lead/LeadServiceIndexTest.php:166`
construct `LeadService` with four positional arguments and are now broken by design.

Report: `docs/ai-reports/lead/STEP-lead-transaction-duplicate-guard.md`
Decisions: D-025, D-026
