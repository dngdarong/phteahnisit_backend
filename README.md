# phteahnisit — Backend Architecture (v0.1 MVP + v0.2, hardened through Phase 5)

## 1. Setup

This package contains hand-written source files only (no vendor/,
composer.lock, or bootstrap cache) — the sandbox this was built in
cannot reach Packagist. To stand it up:

```bash
composer create-project laravel/laravel phteahnisit
cd phteahnisit
composer require laravel/sanctum
```

Then copy this package's `app/`, `database/`, `routes/api.php`,
`bootstrap/app.php`, and `config/phteahnisit.php` over the fresh
project's equivalents (merging `bootstrap/app.php` by hand if Laravel's
scaffold has diverged), copy `.env.example` → `.env`, then:

```bash
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
```

## 2. Decisions made while building this (flagging for review)

Two items were left open in prior review turns. I proceeded with
defaults rather than block on them — override by editing the relevant
file if you disagree:

1. **Room-level "Phone Required"** (FRS Validation Rules) — removed.
   Contact is exclusively via the landlord's `users.phone`, surfaced
   through `Room::landlord` in `RoomResource`. No `phone` column was
   added to `rooms`.
2. **`users.phone` nullability** — left `nullable()` at the DB level
   (unchanged from the original migration), enforced as `required` only
   in `RegisterStudentRequest` / `RegisterLandlordRequest`. This means
   an admin-created account (`AuthController::storeAdmin` path) could
   theoretically exist without a phone, which seems like the right
   flexibility for that path.

Also added, since three separate spec docs required it but no table
existed for it:

3. **`audit_logs` table** (migration `2024_01_01_000004`) — generic
   `(actor_id, action, subject_type, subject_id, metadata)` shape,
   logged via `AuditLogService`, called from `AuthService` (register,
   login, logout, admin-created) and `RoomService` (create, update,
   delete, approve, reject, force-delete).

## 3. Layer responsibilities

Following the modular architecture from the project overview:

- **Controllers** (`app/Http/Controllers/Api/**`) — thin. Validate via
  Form Requests, call a Service, return a Resource. No business logic.
- **Form Requests** (`app/Http/Requests`) — input validation only.
  `authorize()` does a *second*, explicit role check even where route
  middleware already restricts access (defense in depth per the
  Security Rules doc: "never trust a single layer").
- **Services** (`app/Services`) — all business logic. This is where the
  "approved room edited → reverts to pending" rule lives
  (`RoomService::update`), not in the controller or a model event —
  keeping it visible and testable in one place.
- **Policies** (`app/Policies`) — authorization decisions tied to a
  specific model instance (ownership, visibility by status). Called via
  `Gate::authorize()` in controllers.
- **Resources** (`app/Http/Resources`) — response shaping. This is the
  single place that guarantees `password`/`remember_token` never leave
  the API (Security Rules: "never returned in API responses").
- **Enums** (`app/Enums`) — `RoleEnum`, `RoomStatusEnum`,
  `RoomTypeEnum`, `UserStatusEnum`. Single source of truth for every
  status/role string, addressing the Auth doc's "avoid hardcoded role
  strings throughout the project." Route middleware, policies, services,
  and model casts all reference these instead of raw strings.

## 4. Middleware stack

Applied in `bootstrap/app.php` + per-route-group in `routes/api.php`:

| Middleware | Purpose |
|---|---|
| `auth:sanctum` | Valid access token required |
| `active` (`EnsureUserIsActive`) | Re-checked on *every* request, not just login — a token issued before deactivation stops working immediately |
| `role:admin` / `role:landlord` | Route-level role gate, reads `RoleEnum` |

Ownership (a landlord editing only their own room) is **not** a
middleware — it needs the resolved `Room` model, so it's enforced via
`RoomPolicy::update`/`delete` inside the controller, called after route
model binding resolves `{room}`.

## 5. Permission matrix

| Action | Guest | Student | Landlord (own) | Landlord (other's) | Admin |
|---|---|---|---|---|---|
| Browse/search rooms | ✅ | ✅ | ✅ | ✅ | ✅ |
| View room detail (incl. contact) | ✅ | ✅ | ✅ | ✅ | ✅ |
| View pending/rejected room | ❌ | ❌ | ✅ (own) | ❌ | ✅ |
| Create room | ❌ | ❌ | ✅ | — | ❌ |
| Edit room | ❌ | ❌ | ✅ | ❌ | ✅ |
| Delete (soft) room | ❌ | ❌ | ✅ | ❌ | ✅ |
| Force-delete room | ❌ | ❌ | ❌ | ❌ | ✅ |
| Approve/reject room | ❌ | ❌ | ❌ | ❌ | ✅ |
| Edit own profile | ❌ (must register) | ✅ | ✅ | ✅ | ✅ |
| Manage users / disable accounts | ❌ | ❌ | ❌ | ❌ | ✅ |
| Create admin account | ❌ | ❌ | ❌ | ❌ | ✅ |

## 6. Token lifecycle

1. `POST /auth/login` → `AuthService::login` creates a new Sanctum
   personal access token on every successful login (Auth doc: "Each
   login creates a new token").
2. Every subsequent request carries `Authorization: Bearer {token}`.
3. `EnsureUserIsActive` re-validates status per-request; a disabled
   user's token is deleted server-side on their next request, not just
   rejected.
4. `POST /auth/logout` → `AuthService::logout` deletes the current
   token only (single-device logout — multi-device session management
   is explicitly future scope per the Auth doc).

## 7. Error handling

Handled centrally in `bootstrap/app.php`'s `withExceptions()` (left as
a stub here — implement per Laravel's standard JSON exception
rendering): `ValidationException` → 422 with field errors,
`AuthorizationException` → 403, `ModelNotFoundException` → 404,
everything else → 500 with a generic message in production
(`APP_DEBUG=false`), matching the FRS Error Rules ("never expose
technical errors").

## 8. What's deliberately not here (v0.1 scope note)

At v0.1, per the FRS/Business Rules "Future Compatibility" sections: no
Favorites, Booking, Chat, Notifications, Reviews, Payments, or Maps
code existed anywhere in this package. v0.2 built four of those
(Favorites, Bookings, Chat, Map) — see `docs/BACKEND_ARCHITECTURE.md`
section 9 for the full v0.2 schema/service/policy breakdown. Payments,
reviews/ratings, and a standalone notifications system remain out of
scope.

## 9. Hardening (Phases 1–5)

Post-v0.2 hardening pass, business logic and API contracts unchanged:

- **Phase 1 — Security**: `AddSecurityHeaders` middleware, auth rate
  limiting (`AuthRateLimiter`), dedicated security log channel.
- **Phase 2 — Booking workflow**: `BookingService` create/approve/
  reject/cancel wrapped in `DB::transaction()` + `lockForUpdate()`,
  with explicit pending-only guards rejecting invalid state
  transitions (422) instead of silently succeeding.
- **Phase 3 — Data integrity**: `RoomResource`, `BookingResource`, and
  `ConversationResource` now return `null` (not an all-null object) for
  a relation whose parent row was soft-deleted.
- **Phase 4 — API standardization**: same null-safety extended to
  `BookingResource.student` / `ConversationResource.other_participant`;
  a scoped exception-response hook in `bootstrap/app.php` strips debug
  fields (`exception`/`file`/`line`/`trace`) from `api/*` JSON error
  responses.
- **Phase 5**: frontend-only, no backend changes.

No new backend test count is asserted here — see the test suite for
current pass/fail counts at any given commit.
