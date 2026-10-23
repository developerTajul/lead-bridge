# User 01 — User API index and show

- **Date:** 2026-10-20
- **Scope:** Refactor the User API controller so the read side works end to end.
- **Status:** Complete, verified. One destructive incident occurred and was fully reversed — see below.

## What was asked

`refactor usercontroller api fix`.

The existing `Api\V1\UserController` had an index action but no working read path behind it,
and its query route had no detail counterpart.

## What exists in the User module

The module already carried a **write side** before this step: `UserService`, `UserMapper`,
`UserResponseMapper`, `UserEntity`, `UserRepository`, and their DTOs and contracts. All of it
is pre-existing and tracked at `HEAD`. Nothing in this step modified any of it.

## What was built

A **read side**, added beside the write side and sharing no surface with it:

| Layer | File | Purpose |
|---|---|---|
| Domain | `Domain/UserRules.php` | `MIN_PER_PAGE` / `MAX_PER_PAGE` bounds |
| Domain | `Domain/Exceptions/InvalidUserException.php` | Domain rejection, named constructors only |
| Domain | `Domain/ValueObjects/UserFilter.php` | Validated page size; refuses to exist out of bounds |
| Domain | `Domain/DataObjects/StoredUserData.php` | Read model; deliberately has no password field |
| Domain | `Domain/Contracts/UserQueryContract.php` | Read port |
| Application | `Application/DTOs/Requests/UserFilterDTO.php` | Filter DTO |
| Application | `Application/DTOs/Responses/UserListItemResponseDTO.php` | Response DTO, `JsonSerializable` |
| Application | `Application/Mappers/UserListFilterMapper.php` | Query params -> filter DTO, rejects bad page sizes |
| Application | `Application/Mappers/UserListResponseMapper.php` | `StoredUserData` -> response DTO |
| Application | `Application/Services/UserQueryService.php` | Query service: list + detail |
| Infrastructure | `Infrastructure/Repositories/UserQueryRepository.php` | Eloquent read adapter |

Framework wiring:
- `app/Providers/UserServiceProvider.php` — binds the query contract, both read mappers, and the service.
- `app/Http/Controllers/Api/V1/UserController.php` — thin adapter; filter mapping, envelope, `error_code` -> HTTP status.
- `app/Http/Requests/Api/V1/IndexUserRequest.php` — authorises the index call.
- `routes/api.php` — added `GET /api/v1/users/{user}`.
- `bootstrap/providers.php` — registered `UserServiceProvider`.

### Why the read side has its own mapper and DTO

The write side's `UserResponseMapper::toResponseDTO()` takes a `UserEntity` and its
`UserResponseDTO` carries no `created_at`. A listing needs a timestamp and a read model, not
an aggregate. Rather than widen the write-side contract — which would have changed behaviour
the write path depends on — the read side got its own `UserListResponseMapper` and
`UserListItemResponseDTO`. The cost is one duplicated field list; the benefit is that neither
side can break the other.

## The incident

**I overwrote four tracked files without checking whether they already existed.**

While building the read side I assumed I was creating the User module and wrote
`UserMapperContract.php`, `UserResponseDTO.php`, `UserMapper.php`, and
`UserResponseMapper.php` as new files. They already existed and were tracked. `git status`
caught it.

The developer supplied a `git checkout --` restore command. Two of its four paths were
incorrect (`core/User/Infrastructure/Mappers/...` where the real paths are
`core/User/Application/Mappers/...`); I ran `git ls-files` to confirm the real paths, then ran
the corrected command.

**Recovery is verified, not assumed:** `git diff --stat core/User/` is empty and
`git status --short core/User/` lists only untracked new files. Every pre-existing tracked
file in the module is byte-identical to `HEAD`.

**Root cause:** I did not check for existing files before writing. The rule going forward:
never overwrite a file without first confirming whether it exists and whether it is tracked.

## Verification performed

`php -l` on all twelve touched PHP files — no syntax errors.
`composer dump-autoload` — exit 0, 7174 classes.

Container and domain checks (real bootstrap, real database):

| Check | Result |
|---|---|
| Container resolves `UserQueryService` and `UserListFilterMapper` | pass |
| Default page size is 15 | pass |
| `per_page` of `abc` / `0` / `101` rejected | pass |
| `per_page` of `1` / `100` accepted | pass |
| List returns total = 2 with typed DTOs | pass |
| List DTO's JSON carries no `password` key | pass |
| `created_at` is ISO-8601 (`2026-10-03T12:45:36+00:00`) | pass |
| Detail found / missing | pass |
| Missing id reports `USER_NOT_FOUND` | pass |
| `READ_COLUMNS` excludes `password` at the SQL level | pass |

HTTP checks against a real server on port 8112, real seeded admin, real Sanctum token:

| Check | Result |
|---|---|
| `GET /api/v1/users` without a token | 401 |
| `GET /api/v1/users` with a token | 200, `meta.total = 2` |
| Row hides `password` and `email_verified_at`, carries `created_at` | pass |
| `GET /api/v1/users/{id}` | 200 |
| `GET /api/v1/users/999999999` | 404 `USER_NOT_FOUND` |
| `per_page=abc` / `0` / `101` | 422 `USER_INVALID` |
| `per_page=1` | 200 |

Cleanup: scratch token deleted, all three scratch scripts deleted, port 8112 server stopped.

## Architecture audit

- No `Illuminate\*` import exists in any `core/User` Domain or Application file. The only
  framework type reaching Application is `Psr\Log\LoggerInterface` in the service, which is
  a PSR interface, not a framework class.
- `UserFilter` holds the page-size invariant and refuses to be constructed out of bounds;
  the service contains no business rule, only orchestration and error mapping.
- `UserQueryService` is a query service only. It does not mutate state, raise events, or call
  a write method on a repository.
- It returns `Result`-wrapped `UserListItemResponseDTO`s, never an entity or Eloquent model.
- The repository adapter is the only place that knows an Eloquent model or a column list.
- No new infrastructure capability was introduced that would need a new port.

## Deviations to flag

**The read port sits in `Domain/Contracts/` rather than being named `UserQueryContract` as a
repository port.** This follows `AGENTS.md` ("Query Services MAY use a dedicated read-side
Repository/Finder port"), but the module now has two contracts with adjacent names —
`UserRepositoryContract` (write) and `UserQueryContract` (read). Worth a naming decision if
the module grows.

**Pre-existing and out of scope:** `UserService` is not resolvable —
`UserRepositoryContract` is bound nowhere. `git show HEAD:bootstrap/providers.php` has no
User entry and `AppServiceProvider` at `HEAD` has no User binding, so the write path was
never wired up before this step. I did not touch it. It needs its own step.

**Untouched by instruction:** `tests/Unit/User/UserServiceTest.php` references
`UserResponseMapper`. Testing is paused and that file is part of the write side, so it was
left alone. It is unmodified at `HEAD`.
