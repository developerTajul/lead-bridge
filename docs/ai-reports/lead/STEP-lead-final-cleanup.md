# Lead — Final Clean-Up Phase

- **Date:** 2026-10-23
- **Scope:** Three authorised items: repair `UserServiceTest`, apply the D-023 rule to
  `.claude/rules/` §R8, and document the `->refresh()` omission.
- **Predecessors:** `STEP-lead-dto-boundary.md`, `STEP-lead-dto-boundary-expansion.md`

## Item 1 — `UserServiceTest`: 5 errors resolved

The reported cause (`Unknown named parameter $mapper`) was **only the first of two layers**.
Fixing it exposed a second failure the first had been masking, so this item was not the
one-line change it appeared to be.

**Layer 1 — named-argument mismatch.** `UserService::__construct` declares the parameter as
`$responseMapper`; the test passed `mapper:`. Renamed the argument at the call site.

**Layer 2 — the fixture password was not a hash.** With the construction fixed, four tests
failed with *"A user password must be a hash, never a plain-text value."* The fixture used
`password: 'hashed-password'`, and `UserEntity::assertPasswordIsHashed()` rejects anything
`password_get_info()` does not recognise as a real algorithm. A hand-written placeholder
cannot satisfy this — the digest must be structurally valid.

Replaced with a genuine bcrypt digest held in a named constant:

```php
private const STORED_PASSWORD = '$2y$04$tw8QfuH3qzbVhlMOU7MhQe2XkPVxyWUYNh5lUgmPwVh6k33yqOm5S';
```

Confirmed before use: `password_get_info($hash)['algo']` returns `"2y"`, not `null`.
Cost 4 was used because these are throwaway fixtures and the hasher is mocked — the digest
is never verified, it only has to be structurally valid.

**This is not a production defect.** `UserEntity` is correctly refusing plain-text
passwords; the test fixture was simply wrong. No production code was changed for this item.

Result: `tests/Unit/User/UserServiceTest.php` — **5 passed, 11 assertions.**

## Item 2 — D-023 enforced in `.claude/rules/`

The rule proposed in D-022/D-023 was added to
`.claude/rules/05-rich-domain-and-services.txt` **§R8 (REPOSITORY CONTRACT SHAPE)**:

> A repository write method that mutates an existing Entity RETURNS the persisted Entity
> (e.g. `updateStatus(Entity): Entity`), so the Service maps the stored values without a
> second read. A `void` return forces the Service to either map stale pre-write data (a
> wrong timestamp in the response) or issue a redundant read to fetch it back. Neither is
> acceptable. The adapter satisfies this without an extra query: it maps the model the
> write already returned.

**Scope note, recorded deliberately:** AGENTS.md states the AI never edits `.claude/rules/`
unless the step explicitly asks for it, and to propose changes in the report instead. This
step's prompt explicitly asked for it, so the edit is authorised. It is the **only** file
under `.claude/rules/` touched, and the only section changed is §R8.

File integrity verified after the edit: 389 lines, section header intact, end-of-file
footer intact, rule present at line 337.

D-023's "awaiting developer approval — no rule file was edited" note was updated to
**APPLIED**, so the decision record no longer contradicts the repository state.

## Item 3 — `->refresh()` omission documented as D-024

Recorded in `docs/ai-reports/DECISIONS.md` that `LeadRepository::saveStatus()` intentionally
omits `$leadModel->refresh()` before mapping. The entry covers:

- **What the limitation is:** `BaseRepository::updateRecord()` returns the model without
  refreshing, so a column mutated by a database-side mechanism (a trigger, or a
  `DEFAULT ... ON UPDATE` clause Eloquent does not know about) would not appear in the
  mapped Entity.
- **Why it is deliberate:** every sibling module behaves identically —
  `UserRepository::updateStatus()`, `CompanyService`, `DealService`, `PipelineService` all
  map the model the write returned. Refreshing here alone would make Lead the single module
  whose write path costs an extra `SELECT`.
- **The trade-off:** a client would see a pre-trigger value for that column and would have to
  `GET /leads/{id}` to see the post-trigger one. `updated_at` is **not** affected, because
  Eloquent sets it itself and it is therefore in the attributes the write returned.
- **The revisit condition:** if DB-side triggers or generated columns are introduced on an
  Entity's table, `->refresh()` becomes required and D-024 must be superseded.

## Final test suite status

```
{"tool":"phpunit","result":"passed","tests":456,"passed":456,
 "assertions":1218,"duration_ms":12857}
```

**456 tests, 456 passed, 1218 assertions, 0 failures, 0 errors.** The suite is fully green;
no pre-existing failures remain.

Progress across the three phases, measured each time rather than assumed:

| Phase | Result |
|---|---|
| Before any work | 456 tests, 451 passed, 5 errors |
| After DTO boundary + expansion | 456 tests, 451 passed, 5 errors (`UserServiceTest`) |
| After this phase | 456 tests, **456 passed, 0 errors** |

## Verification

- `php -l` clean on `tests/Unit/User/UserServiceTest.php`.
- Hash constant validated directly against `password_get_info()` before being committed to
  the fixture.
- Grep confirmed no other `mapper:` named-argument call sites remain in `tests/Unit/User/`
  or `tests/Feature/User/`.
- Rule file line count, section header, and EOF footer checked after the edit.
- Full suite run twice; both green.

## Not done / open

- **No production code changed in this phase.** Item 1 was a test-fixture defect, item 2 a
  rules edit, item 3 a documentation edit.
- **`->refresh()` was not added** — its omission is the documented decision, not a pending
  fix.
- **The `LeadConversionService`'s own `LeadConversionResponseDTO`** remains a separate
  response shape from `LeadResponseDTO`. Still an open question for the developer, still not
  a defect; unchanged by this phase.
- **`LeadRules.php` carries an unrelated 2-line change** visible in the diff, present before
  this work began, not touched by it.

## Decisions recorded

- **D-024** — `saveStatus()` intentionally omits `->refresh()`; DB-side triggers are not
  observed.
- **D-023** — updated from "awaiting approval" to **APPLIED**.

See `docs/ai-reports/DECISIONS.md`.
