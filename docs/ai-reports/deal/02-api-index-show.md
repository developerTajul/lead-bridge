# Step Report 02 - Deal API Index and Show Methods

- **STEP ID:** 02
- **MODULE:** Deal
- **DATE:** 2026-10-20
- **STATUS:** COMPLETE
- **BRANCH:** main

## Goal

Implement the read-side use cases (`index` and `show`) for the Deal API, including the
relations (`company`, `contact`, `pipeline`, `stage`, `activities`) and the filters
(`pipeline_id`, `deal_stage_id`). Move both actions out of the controller's inline
Eloquent and behind query services and response DTOs.

## Files Created

### Domain (zero framework imports)
| File | Purpose |
| --- | --- |
| `core/Deal/Domain/Contracts/DealQueryContract.php` | Read-side port: filtered list + detail read. |
| `core/Deal/Domain/ValueObjects/DealFilter.php` | Validates its own `pipelineId` / `dealStageId` / `perPage` bounds. |
| `core/Deal/Domain/DataObjects/StoredDealData.php` | A deal entity plus the related rows the list and detail reads carry. |

### Application (zero framework imports)
| File | Purpose |
| --- | --- |
| `core/Deal/Application/DTOs/Requests/DealFilterDTO.php` | Immutable list criteria coming in. |
| `core/Deal/Application/DTOs/Responses/DealDetailResponseDTO.php` | Detail shape: deal + relations + activities. |
| `core/Deal/Application/DTOs/Responses/DealCompanyResponseDTO.php` | Nested company row. |
| `core/Deal/Application/DTOs/Responses/DealContactResponseDTO.php` | Nested contact row. |
| `core/Deal/Application/DTOs/Responses/DealPipelineResponseDTO.php` | Nested pipeline row. |
| `core/Deal/Application/DTOs/Responses/DealStageResponseDTO.php` | Nested stage row (id, name, probability, order). |
| `core/Deal/Application/DTOs/Responses/DealActivityResponseDTO.php` | Nested activity row. |
| `core/Deal/Application/Services/DealQueryService.php` | Query Service: `getPaginatedDeals`, `getDealDetails`. |

### Framework / Delivery
| File | Purpose |
| --- | --- |
| `app/Http/Requests/Api/V1/IndexDealRequest.php` | Query-shape validation (mirrors `IndexContactRequest`). |

## Files Modified

- `core/Deal/Domain/Contracts/DealRepositoryContract.php` — unchanged in its existing
  `save()`; the read methods went to the new `DealQueryContract` so the write port stays
  a write port (CQS).
- `core/Deal/Domain/DealRules.php` — added `MIN_PER_PAGE` / `MAX_PER_PAGE`, mirroring the
  bound `ContactRules` already carries.
- `core/Deal/Infrastructure/Repositories/DealEloquentRepository.php` — now also
  `implements DealQueryContract`, with `getFilteredDeals()` and `findDetailById()`.
- `core/Deal/Application/Mappers/DealResponseMapper.php` — added `toDetailResponseDTO()`
  and the nested-row helpers.
- `core/Deal/Application/Contracts/DealMapperContract.php` — added `mapToFilterDTO()`.
- `core/Deal/Application/Mappers/DealMapper.php` — implements `mapToFilterDTO()`.
- `app/Http/Controllers/Api/V1/DealController.php` — `index()` and `show()` refactored.
  **`store()`, `update()`, `destroy()` bodies untouched.**
- `app/Providers/DealServiceProvider.php` — bound `DealQueryContract` and
  `DealQueryService`.

## Architecture Notes

- **Two ports, not one.** `DealRepositoryContract` stays write-only; the reads sit behind
  `DealQueryContract`. This matches `Contact` / `Pipeline` and keeps a Query Service from
  ever holding a port that can write.
- **The read graph is flattened in Infrastructure.** `DealEloquentRepository` narrows each
  relation to plain column rows and hands them up as `StoredDealData`. Nothing above the
  adapter knows how Eloquent nests a relation, and the response mapper is the only place
  rows become nested DTOs — the same shape `ContactDetailData` uses.
- **Relation rows are narrowed at the query.** `company:id,name`, `contact:id,name`,
  `pipeline:id,name,is_default`, `stage:id,name,probability,order_position` and
  `activities` with its user — so a list read never pulls a whole record it prints two
  columns from.
- **`updated_at` is not re-read after a relation load.** The entity is mapped from the
  deal's own attributes before relations are attached, so `DealResponseDTO` keeps carrying
  the deal's real timestamps.
- **Filters are validated by the Value Object.** `DealFilter` guards the page size the way
  `ContactFilter` does; the mapper rejects a non-numeric `pipeline_id` / `deal_stage_id`
  as bad input before it reaches the domain.
- **`errorStatus()` gained `DEAL_NOT_FOUND` -> 404.** Step 01's helper already had the
  branch; `show` now actually uses it.

## Deliberate Scope Boundaries

- `update` and `destroy` are untouched — Step 03 owns them.
- No web controller or Blade views — Steps 04 and 05 own them.
- No `Domain/Events/` — a read raises none.

## Verification (all evidence from actual execution)

### 1. Syntax — every touched file passes
`php -l` on all created and modified files: **no syntax errors detected**.

### 2. Autoload
`composer dump-autoload` — exit 0.

### 3. Container resolution
`DealQueryContract` -> `Core\Deal\Infrastructure\Repositories\DealEloquentRepository`,
`DealQueryService` -> itself, resolved through the real Laravel container.

### 4. Routes
`php artisan route:list --path=deals` — `deals.index` (`GET api/v1/deals`) and
`deals.show` (`GET api/v1/deals/{deal}`) both registered.

### 5. Command Service behaviour (real DB, real HTTP kernel)
```
INDEX (no filter)     -> 200, total=4, per_page=15, last_page=1
INDEX ?pipeline_id=4  -> 200, total=2  (ids 4, 3 — newest first)
INDEX ?deal_stage_id=6-> 200, total=1  (id 1)
INDEX (both filters)  -> 200, total=0
INDEX ?per_page=999   -> 422 DEAL_INVALID (page size outside 1..100)
INDEX ?pipeline_id=abc-> 422 DEAL_INVALID
SHOW  /deals/1        -> 200, company.name + contact.name + pipeline.name + stage.name
                         + probability all present
SHOW  /deals/999      -> 404 {"status":"error","message":"Deal not found.","error_code":"DEAL_NOT_FOUND"}
```

### 6. Row-count check
`deals` is back to its original **4 rows** after verification; no rows were created,
changed, or deleted by the read paths.

### 7. Portability audit (grep of `core/Deal`)
No `Illuminate|WordPress|DB::|Eloquent|Facade|now()|dispatch(|do_action` import exists in
any Domain or Application file. The only match is the docblock text in
`DealEloquentRepository` and the `App\Models\Deal` / `DealStage` imports that belong to
Infrastructure.

## Definition of Done

- [x] Strict typing on; namespaces match paths.
- [x] Domain has zero framework imports; Application imports only ports/DTOs/Shared.
- [x] All I/O behind a port; the only ORM usage is in Infrastructure.
- [x] Both use cases return `Result`; no exception leaks upward.
- [x] Model <-> Entity conversion goes through a mapper.
- [x] Port -> implementation bound once at the composition root.
- [x] Delivery is thin: validate -> Mapper builds DTO -> use case -> translate Result.
- [x] Query Services mutate nothing, record no events, and call no write method.
- [x] Services return ResponseDTOs (and a `PaginatedResult` of them), never Entities.
- [x] Controller uses guard clauses on `Result->isFailure()`, not ternaries.
- [ ] Tests — **not applicable**, UNIT TESTING POLICY is active.

## Known Limitations

- None outstanding for this step.
