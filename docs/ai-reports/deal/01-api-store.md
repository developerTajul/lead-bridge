# Step Report 01 - Deal API Store Method

- **STEP ID:** 01
- **MODULE:** Deal
- **DATE:** 2026-10-20
- **STATUS:** COMPLETE
- **BRANCH:** main

## Goal

Implement the `store` use case for the Deal API (`DealController@store`) following
the project architecture: thin controller, validation extracted to a FormRequest,
framework-agnostic core, ports bound at the composition root.

## Files Created

### Domain (zero framework imports)
| File | Purpose |
| --- | --- |
| `core/Deal/Domain/DealRules.php` | Shared numeric/format rules for the module. |
| `core/Deal/Domain/Exceptions/InvalidDealException.php` | Domain-specific violations (title, amount, currency, IDs). |
| `core/Deal/Domain/Entities/DealEntity.php` | `readonly` entity with named constructor + guards. |
| `core/Deal/Domain/Contracts/DealRepositoryContract.php` | Persistence port (`save`). |

### Application (zero framework imports)
| File | Purpose |
| --- | --- |
| `core/Deal/Application/DTOs/Requests/StoreDealDTO.php` | Immutable input DTO. |
| `core/Deal/Application/Contracts/DealMapperContract.php` | Request-to-DTO mapping port. |
| `core/Deal/Application/Mappers/DealMapper.php` | Implements the mapping port. |
| `core/Deal/Application/DTOs/Responses/DealResponseDTO.php` | Immutable output DTO. |
| `core/Deal/Application/Mappers/DealResponseMapper.php` | Entity to ResponseDTO. |
| `core/Deal/Application/Services/DealService.php` | Command Service (`createDeal`). |

### Infrastructure
| File | Purpose |
| --- | --- |
| `core/Deal/Infrastructure/Mappers/DealEntityMapper.php` | Model array <-> entity. |
| `core/Deal/Infrastructure/Repositories/DealEloquentRepository.php` | Eloquent adapter for the port. |

### Framework / Delivery
| File | Purpose |
| --- | --- |
| `app/Http/Requests/Api/V1/StoreDealRequest.php` | Validation rules extracted from the controller. |
| `app/Providers/DealServiceProvider.php` | Composition root for the module. |

## Files Modified

- `app/Http/Controllers/Api/V1/DealController.php` — `store()` refactored; constructor
  injection added. **`index`, `show`, `update`, `destroy` bodies untouched.**
- `bootstrap/providers.php` — registered `DealServiceProvider`.

## Architecture Notes

- **Command Service naming.** The brief named `AppServiceProvider` for the bindings.
  The repo's established pattern is one `<Module>ServiceProvider` per module
  (`Contact`, `Pipeline`, `Company`, `Lead`, `Activity` all have one), so bindings went
  into a new `DealServiceProvider` registered in `bootstrap/providers.php`. See Decisions.
- **Money as string.** `amount` travels and persists as a decimal string, never through a
  float cast in the entity. `DealRules::MIN_AMOUNT`/`MAX_AMOUNT` mirror the
  `decimal(15,2)` column.
- **Currency normalisation.** Upper-cased in the mapper so `bdt` and `BDT` are one currency.
- **Repository read-back.** `save()` re-reads the row after insert so the returned entity
  carries the database-assigned id and timestamps.
- **`(int) $data['amount']` avoided** in the entity mapper — the decimal string is kept intact.

## Deliberate Scope Boundaries

- The entity implements **no** update/stage-transition behaviour. Step 03 owns the
  Won/Lost close-date rule; adding it here would duplicate business logic and pre-empt
  that step. This follows the defer rule in `09-autonomous-execution.txt` (A2.2).
- No query service, web controller, or Blade views — those are Steps 02-05.

## Verification (all evidence from actual execution)

### 1. Syntax — 16/16 files pass
`php -l` on every created and modified file: **no syntax errors detected** in all 16.

### 2. Autoload
`composer dump-autoload` — exit code 0, "Generated optimized autoload files containing 7113 classes".

### 3. Container resolution
Resolved through the real Laravel container:
```
Repo:       Core\Deal\Infrastructure\Repositories\DealEloquentRepository
Mapper:     Core\Deal\Application\Mappers\DealMapper
Service:    Core\Deal\Application\Services\DealService
Controller: App\Http\Controllers\Api\V1\DealController
```

### 4. Routes
`php artisan route:list --path=deals` — all 5 API routes registered, `deals.store` -> `POST api/v1/deals`.

### 5. Command Service behaviour (happy path, real DB)
```
SUCCESS: true
MESSAGE: Deal created successfully.
ID: 5
TITLE: [Acme Renewal]      <- trimmed from '  Acme Renewal  '
AMOUNT: '1500.00'          <- normalised from '1500'
CURRENCY: 'BDT'            <- upper-cased from 'bdt'
CREATED_AT: '2026-10-20T09:50:30+00:00'
```

### 6. Command Service failure paths (real DB)
```
BLANK_TITLE   success=false code=DEAL_INVALID      msg=A deal must carry a title.
LONG_TITLE    success=false code=DEAL_INVALID
BAD_CURRENCY  success=false code=DEAL_INVALID      msg=A deal currency must be a 3-letter code, [BD] given.
ZERO_PIPELINE success=false code=DEAL_INVALID
BAD_FK        success=false code=DEAL_CREATE_FAILED
ROWS before=5 after=5 (no partial writes: true)
```
The invalid cases are caught as `InvalidDealException` and mapped to `DEAL_INVALID`; the
FK violation surfaces as the generic `DEAL_CREATE_FAILED` rather than a validation error,
which is correct — the FormRequest's `exists:` rule is what rejects bad foreign keys at
the HTTP boundary.

### 7. HTTP layer through the Laravel kernel
```
VALID CREATE      -> 201  {"status":"success","message":"Deal created successfully.","data":{...id:5, amount:"250.50", currency:"USD"...}}
MISSING TITLE     -> 422  {"message":"The title field is required.","errors":{...}}
BAD FK            -> 422  {"message":"The selected pipeline id is invalid.",...}
```
Note `amount: 250.5` -> `"250.50"` and `currency: "usd"` -> `"USD"` through the HTTP path.

### 8. Authentication enforced (real TCP)
```
POST http://127.0.0.1:8899/api/v1/deals (no token)
STATUS: HTTP/1.1 401 Unauthorized
BODY: {"message":"Unauthenticated."}
```
An earlier in-process kernel check returned 201 for a no-token request. That was an
artifact of reusing one kernel instance in a single process (the authenticated user from
the previous request persisted in the container) — **not** a hole in the route. Verified
over real TCP: 401.

### 9. Portability audit (grep of `core/Deal`)
`Illuminate|WordPress|DB::|Eloquent|Facade|now()|dispatch(|do_action` matched **only** the
docblock text in `DealEloquentRepository` ("Eloquent-backed persistence..."). **No framework
import exists in any Domain or Application file.**

## Data Cleanup

Test rows created during verification (ids 5 and 6) were deleted; `deals` is back to its
original 4 rows. The temporary Sanctum tokens and the scratch token file were removed.

## Definition of Done

- [x] Strict typing on; namespaces match paths.
- [x] Domain has zero framework imports; Application imports only ports/DTOs/Shared.
- [x] All I/O behind a port; the only ORM usage is in Infrastructure.
- [x] Use-case method returns `Result`; no exception leaks upward.
- [x] Model <-> Entity conversion goes through a mapper.
- [x] Port -> implementation bound once at the composition root.
- [x] Delivery is thin: validate -> Mapper builds DTO -> use case -> translate Result.
- [x] Business rules live in the Entity; the Service only orchestrates.
- [x] Service is Command-only; no query method mixed in.
- [x] Service returns a ResponseDTO inside `Result`.
- [x] Controller uses guard clauses on `Result->isFailure()`, not ternaries.
- [x] `composer dump-autoload` run after adding new namespace paths.
- [ ] Tests — **not applicable**, UNIT TESTING POLICY is active.

## Known Limitations

- `core/Deal` has no `Domain/Events/` yet — the create use case raises no domain event.
  Events will be introduced when a use case needs one.
- The entity carries no `domainEvents` list, since nothing raises an event in this step.
  When a behaviour method is added that does, the list and `releaseEvents()` come with it.
- Verification was done by executing the use case and the HTTP kernel directly, not by a
  test suite (testing is paused).
