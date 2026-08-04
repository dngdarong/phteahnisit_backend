# Release Notes — Backend

## v0.3.2 (current)

Covers the hardening, QA, and performance work done on top of the v0.2
feature baseline (Favorites, Bookings, Chat, Map). Business logic and
API contracts are unchanged from v0.2 throughout this entire sequence
unless a phase explicitly says otherwise below.

### Phase 1 — Security Hardening
`AddSecurityHeaders` middleware, auth-endpoint rate limiting
(`AuthRateLimiter`), dedicated security log channel.

### Phase 2 — Booking Workflow Hardening
`BookingService` create/approve/reject/cancel wrapped in
`DB::transaction()` + `lockForUpdate()`, with explicit pending-only
guards that reject invalid state transitions (422) instead of silently
succeeding.

### Phase 3 — Room Lifecycle & Data Integrity
`RoomResource`, `BookingResource`, and `ConversationResource` now
return `null` (not an all-null placeholder object) for a relation whose
parent row was soft-deleted.

### Phase 4 — API Standardization
Same null-safety extended to `BookingResource.student` /
`ConversationResource.other_participant`; a scoped exception-response
hook in `bootstrap/app.php` strips debug fields
(`exception`/`file`/`line`/`trace`) from `api/*` JSON error responses.

### Phase 5 — Frontend Stability
Frontend-only; no backend changes.

### Phase 5.6 — Release Hygiene
`.gitignore` entries for stray local files, `.env.example` comments
flagging production-unsafe defaults (`APP_DEBUG`, `SEED_DEMO_DATA`),
stale README/architecture-doc corrections.

### Phase 6 — QA Foundation
Closed test-coverage gaps that existed alongside otherwise-solid
auth/authz/business-logic coverage: FormRequest field-validation
boundaries, pagination-envelope shape, and 404 consistency across
resource types beyond Room. 28 new tests
(`tests/Feature/Validation/*`, `tests/Feature/Api/PaginationAndNotFoundTest.php`).
Zero application code changed.

**Result: 147/147 Pest tests passing** (up from 119).

### Phase 7 — Performance Optimization
Full audit found no N+1 queries and adequate indexes already in place
— the codebase was already close to clean. One safe, evidence-based
fix applied: `RoomService::attachImages()` no longer runs a redundant
`COUNT(*)` query when called from `create()`, where a brand-new room
provably has zero existing images (`update()`'s count-derived path is
untouched). Verified via query log: 6→5 queries per room-create
request.

Two higher-impact findings were identified but **not applied**, since
fixing them would change the API response contract (out of scope for a
behavior-preserving performance pass):
- `ConversationController::index` eagerly embeds full message history
  per conversation — ~66% avoidable payload on conversation lists.
- `RoomController::map` runs an unbounded query with no pagination —
  harmless at current scale, a real risk as the room table grows.

No schema changes, no test-file changes. 147/147 tests passing
throughout.

---

## Current status (as of v0.3.2)

- **Tests**: 147/147 Pest tests passing
- **API contracts**: unchanged since v0.2
- **Database schema**: unchanged since v0.2
- **Known remaining bottlenecks**: see Phase 7 section above — both
  documented as requiring a deliberate future API change, not silently
  deferred.

## Earlier history

- **v0.2** — Favorites, Bookings, Chat, room Map features added.
- **v0.1** — Initial MVP (auth, room search/CRUD, admin moderation).
