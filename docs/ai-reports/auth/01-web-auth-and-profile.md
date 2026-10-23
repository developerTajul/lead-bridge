# Step Report 01 - Web Auth Routes and Profile

- **STEP ID:** 01
- **MODULE:** Auth
- **DATE:** 2026-10-20
- **STATUS:** COMPLETE
- **BRANCH:** main

## Goal

Extend the Auth module's web side and put the profile read (`AuthService::getProfile`)
behind a real page for both delivery channels.

The survey found the web Auth controller already existed and already worked —
login, register and logout were complete and passing. The genuine gaps were:

1. There was **no web profile route or view at all**. `getProfile()` was reachable
   only from the API.
2. `Api\V1\AuthController::me()` called `getProfile()` but **ignored the `Result`
   entirely** — it returned `getData()` with a hardcoded `'status' => 'success'`
   even when the service had failed, so a failure would render
   `{"status":"success","data":null}` with HTTP 200.
3. The profile links in the sidebar and the header dropdown pointed at `#` and a
   dead `profile.html`, so the new page had no way in.

## Files Created

| File | Purpose |
| --- | --- |
| `core/Auth/Application/DTOs/Requests/ProfileRequestDTO.php` | Carries the user id a profile read is for. |
| `resources/views/auth/profile.blade.php` | The profile page, on the admin design system. |

## Files Modified

| File | Change |
| --- | --- |
| `core/Auth/Application/Contracts/AuthMapperContract.php` | Added `mapToProfileDTO(int $userId)`. |
| `core/Auth/Application/Mappers/AuthRequestMapper.php` | Implements it. |
| `core/Auth/Application/Services/AuthService.php` | `getProfile()` now takes `ProfileRequestDTO` instead of `int $userId`. |
| `app/Http/Controllers/Web/Backend/AuthController.php` | Added `me()`. |
| `app/Http/Controllers/Api/V1/AuthController.php` | `me()` given a failure branch and the mapper call. |
| `routes/web.php` | `GET /profile` named `profile`, inside the `auth` group. |
| `resources/views/layouts/partials/asidebar.blade.php` | Profile entry points at the route and highlights when active. |
| `resources/views/layouts/partials/header.blade.php` | Dropdown profile link points at the route. |

## Design Notes

### No new Service or Controller was needed

The prompt allowed for "`AuthController` or equivalent web counterpart", and one
already existed at `App\Http\Controllers\Web\Backend\AuthController`. It is the
web counterpart to `Api\V1\AuthController` and already followed the canonical
flow. Creating a second web auth controller under `App\Http\Controllers\Auth\`
would have produced two controllers handling the same three use cases, so the
existing one was extended instead. The `Auth` exception in
`.claude/rules/02-framework-adapter-laravel.txt` (single-use-case service
architecture, `LoginRequestDTO`-style naming) was followed rather than
"corrected" — that file marks it as intentional for this module only.

### The controller injects the mapper, not Core directly

`me()` is:

```php
$profileDTO = $this->mapper->mapToProfileDTO((int) $request->user()->id);
$result = $this->authService->getProfile($profileDTO);

if ($result->isFailure()) {
    return redirect()->route('dashboard')->with('error', $result->getMessage());
}

return view('auth.profile', ['profile' => $result->getData()]);
```

The controller assembles no DTO itself (rule 02, section 2.1), branches on
`Result` with a guard clause, and hands the ResponseDTO to the view. `getData()`
is an `AuthResponseDTO`, a plain readonly class, so the view consumes no Entity
and no Eloquent model.

### `$request->user()` is used only to resolve identity

`$request->user()` returns the Eloquent `App\Models\User`. Its id is read and
immediately converted to an `int`; nothing else about the model is touched. There
is no port for "the current session identity" in Core today, and inventing one
was outside this step. This is recorded as **D-013**.

### The page renders the ResponseDTO, not the session user

The view is fed `$profile` from the Service, so what it shows is what
`AuthService::getProfile()` returned. Reading `auth()->user()` instead would have
shown session data that the service had not vouched for, and would have made the
new route pointless. The header and sidebar still use `auth()->user()` for the
avatar and name — that is pre-existing and was left alone.

### Status colouring

The view colours `approved` green and everything else amber. The rule saying
which statuses are "active" lives in the domain
(`AuthService::login()` compares against `AuthStatus::APPROVED`); the view only
picks a colour for a value it was given and decides nothing. The comparison is
against the DTO's string, not the enum, because the ResponseDTO carries plain
scalars by mandate.

## Verification (all evidence from actual execution)

### 1. Syntax

`php -l` clean on all 7 changed PHP files. `composer dump-autoload` exit 0
(**7161** classes, one more than the 7160 before this step).

### 2. Route registered

```
GET|HEAD profile .. profile › Web\Backend\AuthController@me
 ⇂ web
 ⇂ Illuminate\Auth\Middleware\Authenticate
```

The `auth` middleware is on the route, so an unauthenticated hit is redirected
before the controller runs.

### 3. Guest is turned away

```
GET /profile (guest) -> 302  Location: http://127.0.0.1:8110/login
```

### 4. The page, over real HTTP as a session

```
GET  /login              -> 200
POST /login (admin)      -> 302  Location: /dashboard
GET  /profile (authed)   -> 200, 143,077 bytes
  title "Profile":        present
  name "Admin":           present
  email:                  present
  role "ADMIN":           present
  status "APPROVED":      present
  stack trace:            none
  unrendered Blade:       none
```

Every value ties to the row: `admin@example.com` is user #7, `role=ADMIN`,
`status=APPROVED`.

### 5. The full web journey, start to finish

A member registered through the real form, logged in, viewed their own profile,
and logged out:

```
POST /register (name/email/password/password_confirmation) -> 302 -> /login
POST /login                                                -> 302 -> /dashboard
GET  /profile                                              -> 200, 143,211 bytes
  "Verify Member":      present
  the registered email: present
  "MEMBER":             present
  "APPROVED":           present
  badge:                user #45
POST /logout                                               -> 302 -> /login
GET  /profile after logout                                 -> 302 -> /login
```

Note the member registration produced `status=APPROVED` and `role=MEMBER` — those
are the DB defaults mirrored by `RegisterRequestDTO`, not a rule this step added.

### 6. The profile links render everywhere

```
/dashboard     -> 200, profile links rendered: 2
/admin/deals   -> 200, profile links rendered: 2
/admin/leads   -> 200, profile links rendered: 2
/profile       -> 200, profile links rendered: 2
```

Two links per page — the sidebar entry and the header dropdown. The sidebar item
carries `active-page` when the current route is `profile`:

```html
<li class="mx-sidenav__menu-item  active-page ">
    <a href="http://127.0.0.1:8110/profile" class="mx-sidenav__menu-link">
```

and does not carry it on `/dashboard`.

### 7. The API `me()` endpoint

```
GET /api/v1/auth/me (no token) -> 401
GET /api/v1/auth/me (token)    -> 200
{"status":"success","message":"Profile fetched successfully.",
 "data":{"id":7,"name":"Admin","email":"admin@example.com",
         "role":"ADMIN","status":"APPROVED"}}
```

### 8. The API failure branch, now handled

Driven directly against the service:

```
getProfile(userId: 999999)
  isFailure = true
  message   = "User not found."
  errorCode = AUTH_USER_NOT_FOUND
  data      = NULL
```

The controller now returns 404 with the message for this case. Before this step
it would have returned HTTP 200 with `{"status":"success","data":null}`.

### 9. No regression in the other endpoints

```
POST /api/v1/auth/login (good credentials) -> 200, token + user, unchanged
POST /api/v1/auth/login (bad password)     -> 422, "Invalid email or password."
POST /api/v1/auth/register (bad input)     -> 422, same four validation errors
```

### 10. Portability

```
framework imports in core/Auth/{Domain,Application}:  none
App\Models referenced in Domain/Application:          none
declare(strict_types=1); on all 15 core/Auth files:   all present
```

### 11. Cleanup

```
scratch API token deleted: 1   (11 pre-existing tokens untouched)
verification member row removed: 1
users now: 2   (ids 44, 7 — the original rows)
admin password still matches seeder: yes
port 8110 dev server stopped
```

No pre-existing row was created, modified or deleted by the verification.

## Definition of Done

- [x] Strict typing on; namespaces match paths.
- [x] The web controller is thin: mapper -> one service call -> `Result` branch -> view/redirect.
- [x] No Eloquent call, business rule or DTO assembly in either controller.
- [x] Both delivery channels call the same `AuthService::getProfile()` and get the same ResponseDTO.
- [x] The profile route sits behind the `auth` middleware.
- [x] The view receives a ResponseDTO, never an Entity or Eloquent model.
- [x] Guard clauses on every failure path; no ternary for a branch.
- [x] No framework import in `core/Auth` Domain or Application.
- [x] No test file was created — UNIT TESTING POLICY is active.

## Decisions Recorded

- **D-013** The web profile controller reads the current user's id from the session
  and converts it to an int; there is no Core port for session identity yet.

See `docs/ai-reports/DECISIONS.md`.

## Open Items for the Developer

- **The API `me()` response gained a `message` key that it did not have before.**
  It previously returned only `status` and `data`. The key is additive, so a
  client reading `data` is unaffected, but any client doing a strict shape
  comparison would see a difference. This is the one deliberate change to an
  existing API response in this step; it was taken so that `me()` matches
  `login()` and `register()`, which both return `message`.
- **Profile editing was not built.** The prompt's task 2 is a profile *read*
  (`getProfile`), and no update use case exists in `AuthService`. Adding one means
  a new DTO, a new port method and a new form, which is a separate step. The
  profile page is read-only and has no edit control.
- **The register form has an `image` field in `RegisterRequest` and
  `enctype="multipart/form-data"` on the form, but no file input is rendered**, so
  the field is never populated. Pre-existing, untouched, and worth a look.
- D-013 and the earlier open decisions (D-001, D-002, D-005, D-009, D-010, D-011,
  D-012) propose `AGENTS.md` clarifications and still await approval.
