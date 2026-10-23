# Lead — DTO Boundary, Scope Expansion

- **Date:** 2026-10-23
- **Scope:** Three authorised items: execute D-022 (stale timestamp fix), repair the two
  named broken test classes, and enforce rule 02 §7 in `LeadRepository`.
- **Predecessor:** `docs/ai-reports/lead/STEP-lead-dto-boundary.md`

## Item 1 — D-022 executed: `saveStatus()` returns the stored Entity

`LeadRepositoryContract::saveStatus()` changed from `void` to `LeadEntity`, and the
adapter now returns the row rebuilt from storage. `LeadService::changeStatus()` maps that
fresh entity, so `updated_at` reflects the write. `LeadConversionService::convert()` was
updated to consume the new return value — it was a required consumer, not optional churn.

`updateRecord()` already returned the saved model, so no extra query was needed; the
change was to map and return it instead of discarding it.

A row that disappears between the load and the write now throws `RuntimeException` rather
than silently reporting success. Silent failures are forbidden by AGENTS.md.

**Verified against the real database** (row 16, then restored):

```
before: status=new        updated_at=2026-10-05 10:13:16
after : status=contacted  updated_at=2026-10-23 09:46:47
```

And the Service layer serves it:

```json
{"id":16,"status":"contacted","created_at":"2026-10-05T10:13:16+00:00",
 "updated_at":"2026-10-23T09:46:47+00:00"}
```

Row 16 was restored to `status=new`, `updated_at=2026-10-05 10:13:16`. Note the write may
have left an `UPDATE` in the audit trail (no soft deletes on this model); the row values
are original.

### Consequence worth knowing

`LeadEntity` is `final`, and **PHPUnit cannot double a final class to invent a return
value**. Any mock of a method returning `LeadEntity` must now supply one explicitly via
`willReturnCallback`. This is not a defect but it is a real friction cost of the change,
and it is why both test classes needed edits. Recorded as D-023.

## Item 2 — The two test classes

**The instruction's stated cause did not match the code.** Audited before editing:

- `LeadEntity::$status` is **not a promoted parameter** — it is a plain private property
  assigned in the constructor body. Reflection confirms `status promoted=false` while every
  other parameter is `promoted=true`. The tests pass `status:` as a named argument, which is
  plain PHP 8 named-argument syntax and entirely legal.
- Named-argument construction of the entity **succeeds**. Proven directly:
  `named-arg construction: OK`.
- So there was no "incorrect instantiation method" to correct, and removing those named
  arguments would have changed nothing.

The real causes, from a measured baseline (`5 passed, 3 failed, 3 errors` across the two
classes) and fixed instead, per the developer's decision:

| Cause | Fix |
|---|---|
| `LeadConversionServiceTest` stubbed `TransactionManagerContract::run()`, a method that **does not exist** on the port. The service body calls `beginTransaction`/`commit`/`rollback`. `$cb()` never ran, so `convert()` returned the generic failure. | Stub removed; the real three methods are mocked (`beginTransaction`/`commit` default to null, `rollback` asserted in the refusal test). |
| `saveStatus()` returning a `final` class — new, caused by item 1 | Added `stubSaveStatus()` helper / inline `willReturnCallback` returning a real `LeadEntity` with the requested timestamp. |
| `expects()` on `saveStatus` **overrides** the `method()` stub's return value, and a bare `expects()` has none | Where both are needed, the callback is now attached to the `expects()` chain, so it asserts the argument *and* supplies the return value. |
| `LeadStatusChangeTest` asserted `getData()` was a `LeadEntity` | Now asserts `LeadResponseDTO` with `status`/`status_label` (authorised). |
| No coverage existed for the D-022 fix | Added `test_it_reports_the_post_write_timestamp`, which fails if the service regresses to mapping the pre-write entity. |

Result: **`tests/Unit/Lead` + `tests/Feature/Lead` — 133 passed, 319 assertions, 0 failures.**

## Item 2b — `ApiLeadStatusUpdateTest` (consequence of the earlier authorised controller change)

Not named in the brief, but the previous step's removal of the redundant read-back in
`Api\V1\LeadController::updateStatus()` left this class's stub returning `data: null` from
`changeStatus()` and routing the payload through `getLeadById()`. Two failures, both
`Expected 200/400` mismatches. Fixed to match the current contract:

- The stub's `changeStatus()` now returns a real `LeadResponseDTO`.
- `test_it_reports_a_failure_when_the_read_back_fails` described a code path that no longer
  exists. Rewritten as `test_it_answers_from_the_write_without_reading_the_lead_back`,
  which asserts the write alone answers the request — a stronger claim than the one it
  replaced, since it now proves the read port is not consulted.

## Item 3 — Rule 02 §7 enforced in `LeadRepository`

All six `$leadModel->toArray()` call sites became `$leadModel->getAttributes()`. This
matters concretely: **the `Lead` model declares no `$casts`**, so under `getAttributes()`
the mapper receives raw DB strings for every value. Verified the mapper handles that
without relying on Eloquent casts — `toEntity()` produced a valid entity and
`toProjection()` emitted `2026-10-05T10:13:16+00:00`.

`LeadData::toArray()` at line 46 is a **domain value object**, not an Eloquent model, and was
correctly left alone — rule 02 §7 governs models.

Confirmed clean: the only remaining `toArray()` anywhere in `core/Lead/Infrastructure/` is
that one domain call.

## Verification

- `php -l` clean on all 9 changed files.
- Grep of `core/Lead/Infrastructure/` — zero model `toArray()` remain.
- Grep of `saveStatus` across `core/` and `app/` — contract, adapter, and both call sites
  consistent; no stale `void` consumer.
- Real-DB round trip proving the timestamp fix (above).
- `tests/Unit/Lead` + `tests/Feature/Lead`: **133 passed, 0 failures**.
- Full suite: **451 passed, 5 errors** — the 5 are `UserServiceTest`, **pre-existing**.
  Proven by stashing all changes, running that class on the clean tree (5 passed), then
  restoring. They fail with `Unknown named parameter $mapper` and are unrelated to Lead.

## Not done / open

- **The 5 `UserServiceTest` errors were not touched.** They are outside the requested scope
  and pre-existing. `UserService::__construct` no longer accepts a `$mapper` parameter that
  the test still passes.
- **`->refresh()` not called** in `saveStatus()`, so a DB-side `ON UPDATE` trigger would not
  be reflected. Matches every sibling module; noted in D-023.
- **D-023's proposed rule-file line is not applied.** `.claude/rules/` was not edited.
- The `updated_at` test for `createLead()` was not added — `create()` already returned the
  persisted entity, so nothing changed there and no regression is possible.

## Decisions recorded

- **D-023** — `saveStatus()` returns the stored Entity; **supersedes D-022**.

See `docs/ai-reports/DECISIONS.md`.
