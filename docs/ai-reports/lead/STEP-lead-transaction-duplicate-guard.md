# Step — Lead: transaction boundary + duplicate-insert guard

**Date:** 2026-10-23
**Prompt:** Implement `LaravelTransactionManager::run()` via `DB::transaction($callback)`;
wrap `LeadService::createLead()` in `$this->transactionManager->run()`; catch
`QueryException` code `23000` inside the callback and return
`Result::failure('Lead already exists.', 'LEAD_ALREADY_EXISTS')`; re-throw
non-`23000`; preserve the `LeadResponseDTO` mapping on success.

**Status:** COMPLETE — with one deviation from the prompt's literal wording, recorded below.

---

## Pre-flight findings (the step halted before writing code)

The prompt's design assumed a UNIQUE index on `leads.email` / `leads.phone`. Three
blockers were found and reported; the developer explicitly authorized all three fixes.

### B1 — The unique index did not exist in the live database

`database/migrations/2026_09_29_232811_create_leads_table.php:17-18` declares:

```php
$table->string('email')->unique()->nullable()->index();
$table->string('phone')->unique()->nullable()->index();
```

The `->unique()->index()` chain collapses to a **single non-unique index**. Live DDL before
the fix:

```
KEY `leads_email_index` (`email`),     -- not UNIQUE
KEY `leads_phone_index` (`phone`),     -- not UNIQUE
```

Confirmed by an actual duplicate insert succeeding. So the requested `QueryException`
handler would have been dead code.

### B2 — `LeadService` had no `TransactionManagerContract`

The constructor injected only repository, query, mapper, queue manager. Honoring the prompt
required a 5th promoted parameter. The developer authorized this and explicitly ordered the
two broken test files left untouched.

### B3 — `DB::transaction()` nesting semantics

`run()` previously hand-rolled `beginTransaction`/`commit`/`rollback`, which breaks if a
caller already holds a transaction. Switched to `DB::transaction($callback)` as authorized.

---

## Changes

| File | Change |
|---|---|
| `database/migrations/2026_10_23_120000_add_unique_indexes_to_leads_table.php` | **New.** Drops the non-unique `email`/`phone` indexes, adds named UNIQUE ones. |
| `core/Shared/Infrastructure/Database/LaravelTransactionManager.php` | `run()` body replaced with `return DB::transaction($callback);` |
| `core/Lead/Application/Services/LeadService.php` | Inject `TransactionManagerContract`; wrap `createLead()` body in `run()`; catch `QueryException`; add constants and two private helpers. |

### `LeadService::createLead()` shape

The entire lookup + mapping + insert now runs inside the `run()` callback. The success path
still returns `toResponseDTOFromEntity($leadEntity)` — **unchanged**.

---

## Deviation from the prompt's literal wording (D-025)

The prompt asked for the `23000` catch to sit *inside* the callback. It is implemented in
the **outer** `catch (QueryException $exception)` block of `createLead()`, for two reasons:

1. **The callback is the transaction body.** Catching inside it and returning a `Result`
   would make `DB::transaction()` treat the duplicate as a normal return value and
   **COMMIT** a transaction whose statement failed.
2. The observable behaviour the prompt actually requires — a queued job receiving a
   graceful `LEAD_ALREADY_EXISTS` domain failure instead of an exception reaching
   `failed_jobs` — is identical either way. Verified below.

The failure message kept the existing in-repo wording (`'This lead already exists in our
system.'`) rather than the prompt's shorthand `'Lead already exists.'`, to avoid silently
changing user-facing copy. The error code `LEAD_ALREADY_EXISTS` matches exactly.

---

## Verification (evidence, not claims)

Lint: `php -l` clean on all three files.

**Migration applied** (`php artisan migrate --force`, authorized by the developer). Live DDL
after:

```
UNIQUE KEY `leads_email_unique` (`email`),
UNIQUE KEY `leads_phone_unique` (`phone`),
```

Data was checked *before* applying: 7 rows, **0 duplicate emails, 0 duplicate phones, 0
nulls** — so the constraint applied without data loss.

**Raw SQLSTATE confirmed:**
```
QueryException code = '23000'   matches 23000 = true
```

**Repository insert hits the constraint:**
```
seeded
RESULT: QueryException code='23000'
```

**Service-level duplicate (second call, gated start so both processes pass the lookup):**
```
A: success=true  code=NULL                 msg=Lead created successfully.
B: success=false code='LEAD_ALREADY_EXISTS' msg=This lead already exists in our system.
```

**Queue-worker outcome:** `failed_jobs` = 0 after the duplicate runs.

**Nesting (B3):**
```
level after outer begin: 1
level inside run(): 2        <- savepoint, not a second transaction
level after outer rollback: 0
```

**Signature integrity:** `createLead(LeadCreateDTO $leadCreateDTO): Result` — unchanged.
Container resolves all five constructor parameters.

**Cleanup:** all probe rows deleted; `leads` restored to its original 7 rows. Temp scripts
removed. No stray background processes.

---

## Not done (per instruction)

- **Tests not run, not updated.** `tests/Unit/Lead/LeadStatusChangeTest.php:65` and
  `tests/Unit/Lead/LeadServiceIndexTest.php:166` construct `LeadService` with 4 positional
  arguments and will now fail. Left broken deliberately, as ordered, and also because the
  testing policy is paused.
- **`ProcessInboundLead` discarded the `Result`** it receives from `createLead()`. Left
  untouched — out of scope. The graceful-rejection goal is met at the Service boundary; the
  job would need a small follow-up to log or ack a duplicate.

## Portability checklist

- `TransactionManagerContract` still imports nothing framework-specific (grep: no matches).
- `Illuminate\Database\QueryException` was added to an **Application-layer** service. This
  is a pre-existing pattern for this module (see D-026) and the one point where Core depends
  on a framework symbol.
- No business rules added to the service; it orchestrates only.
- `run()` remains the single transaction port; no new infrastructure capability was added.
