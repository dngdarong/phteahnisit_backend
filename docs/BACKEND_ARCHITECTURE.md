# phteahnisit — Backend Architecture (v0.1 MVP + v0.2)

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
code existed anywhere in this package. The schema and route structure
were checked in the prior review turns to confirm none of those need a
redesign of `users`/`rooms`/`room_images`/`audit_logs` to bolt on later
— v0.2 (below) is exactly that bolt-on, with no changes to the v0.1
tables beyond adding `latitude`/`longitude` to `rooms`.

## 9. v0.2 additions — Favorites, Bookings, Chat, Map

Four features, each following the identical layer pattern from section
3 (thin controller → Form Request → Service → Policy → Resource). No
new architectural pattern was introduced; the goal was consistency with
v0.1, not novelty.

### 9.1 Schema

- `rooms.latitude` / `rooms.longitude` — nullable `decimal(10,7)`,
  indexed together. A landlord isn't required to pin a location; the
  map endpoint just excludes rooms without coordinates.
- `favorites` — `(user_id, room_id)`, unique pair, cascade-deletes with
  either side.
- `bookings` — `room_id`, `student_id`, `move_in_date`,
  `duration_months`, `status` (`BookingStatusEnum`: pending / approved
  / rejected / cancelled). No DB-level uniqueness constraint on
  `(room_id, student_id)` — a student can have multiple *historical*
  bookings on the same room (e.g. rejected, then re-applies), just not
  two simultaneously-pending ones. That's enforced in
  `BookingService::create` (an explicit existence check), not the
  schema, because "pending" is a status value, not a row-uniqueness
  property.
- `conversations` — one thread per `(room_id, student_id)` pair
  (unique), `landlord_id` denormalized onto the row so the thread
  survives independent of `rooms.landlord_id` changing.
- `messages` — belongs to a conversation and a sender, plus `read_at`.

### 9.2 New Enums, Models, Policies, Services

- `BookingStatusEnum` — same pattern as `RoomStatusEnum`.
- Models: `Favorite`, `Booking`, `Conversation` (with an
  `isParticipant(User $user)` helper used by its Policy), `Message`.
  `Room` gained `favorites()`/`bookings()`/`conversations()`
  relations and a `scopeOnMap()` (searchable + non-null coordinates).
- `FavoritePolicy` — `create()` is a student-only role gate (mirrors
  `RoomPolicy::create`); `delete()` checks the favorite belongs to the
  requesting user. Landlords/admins are blocked at the API level, not
  just hidden in the UI — confirmed with the user rather than assumed,
  since a landlord/admin favoriting a room has no product meaning.
- `BookingPolicy` — `create()` is student-only; `cancel()` requires
  both ownership *and* `status === pending`; `moderate()` (used for
  both approve and reject) requires `user->isLandlord() &&
  user->id === booking->room->landlord_id` — i.e. bookings are
  reviewed by the room's own landlord, not the admin queue. This is a
  deliberate split from room moderation (admin-only): rooms are a
  platform-quality gate, bookings are a landlord/student negotiation.
- `ConversationPolicy` — `view()`/`send()` both require
  `isParticipant()`. Unlike every other policy in this codebase, there
  is **no admin bypass** — confirmed with the user, since the design
  bundle's chat spec says "only the two participants," full stop.
- `FavoriteService::toggle()` — add or remove based on current state,
  audit-logs `favorite.added` / `favorite.removed`.
- `BookingService` — `create()` rejects a room that isn't
  approved+available, and rejects a second pending booking on the same
  room by the same student (both confirmed decisions, not v0.1-derived
  rules, since no v0.1 spec covered bookings). `approve()`/`reject()`/
  `cancel()` all audit-log. Room edits that revert an approved room to
  pending (`RoomService::update`) do **not** touch existing bookings on
  that room — a booking already in flight is independent of the room's
  re-approval status; also a confirmed decision.
- `ChatService::startOrSend()` — `firstOrCreate` on
  `(room_id, student_id)`, so a second message from the same student
  about the same room reuses the existing thread instead of creating a
  duplicate. `send()` updates `last_message_at`. `markRead()` marks
  every message *not* sent by the reader as read when they open the
  thread. No audit logging on individual messages — matching the
  explicit audit-action list (`booking.*`, `favorite.*` only); chat
  volume would make per-message audit rows noise, not signal.

### 9.3 Routes and middleware

- `GET /rooms/map` — public, registered **before** `GET /rooms/{room}`
  (route-order matters: `{room}` would otherwise swallow the literal
  `map` segment as a room ID lookup).
- Favorites and student-side Booking routes sit behind
  `role:student` — same defense-in-depth as v0.1 (route middleware +
  a second explicit check in the Policy/Service).
- Landlord-side booking review routes
  (`/landlord/bookings*`) sit behind `role:landlord`, structured as a
  dedicated `Api\Landlord\BookingController` — mirrors the existing
  `Api\Admin\*` controller-per-role-scope pattern rather than
  overloading the student-facing `BookingController`.
- Conversation routes sit in the general `auth:sanctum + active` group
  (no role restriction), because a conversation's participants can be
  either a student or a landlord — the real boundary is
  `ConversationPolicy`, resolved from the route-bound `Conversation`.

### 9.4 Permission matrix additions

| Action | Guest | Student | Landlord (own room) | Landlord (other's room) | Admin |
|---|---|---|---|---|---|
| Favorite/unfavorite a room | ❌ | ✅ | ❌ | ❌ | ❌ |
| View own favorites | ❌ | ✅ | — | — | — |
| Request a booking | ❌ | ✅ | — | — | — |
| Cancel own pending booking | ❌ | ✅ (own) | — | — | — |
| Approve/reject a booking | ❌ | ❌ | ✅ | ❌ | ❌ |
| Message a landlord about a room | ❌ | ✅ (starts thread) | — | — | — |
| Read/reply in a conversation | ❌ | ✅ (if participant) | ✅ (if participant) | ❌ | ❌ (no bypass) |
| View rooms with a pinned location | ✅ | ✅ | ✅ | ✅ | ✅ |
| Set a room's coordinates | ❌ | — | ✅ (own, create/edit) | ❌ | ❌ |

### 9.5 What's still deliberately not here

No payments, reviews/ratings, or a standalone notifications system —
chat covers its own "new message" signal via `unread_count` on the
conversation resource, computed on read rather than pushed. No map SDK
dependency: the map endpoint returns coordinate data; the frontend
renders it as a pin-list with links out to Google Maps rather than an
embedded interactive map, per an explicit scope decision to avoid
adding a new third-party dependency for v0.2.
