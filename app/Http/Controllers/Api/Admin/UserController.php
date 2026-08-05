<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterLandlordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** All routes behind ->middleware(['auth:sanctum', 'active', 'role:admin']). */
class UserController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::query();
        $query->when($request->query('role'), fn ($q, $role) => $q->where('role', $role));
        $query->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        return UserResource::collection($query->latest()->paginate(20))->response();
    }

    /**
     * Business Rules: "Only an existing Admin can create another Admin."
     * Reuses the landlord/student registration validation shape (name,
     * email, phone, password) since an admin account needs the same
     * fields - just a different role and creation path.
     */
    public function storeAdmin(RegisterLandlordRequest $request): JsonResponse
    {
        Gate::authorize('createAdmin', User::class);

        $admin = $this->authService->createAdmin($request->user(), $request->validated());

        return (new UserResource($admin))->response()->setStatusCode(201);
    }

    /**
     * v0.2: general "create a user of any role" path. Admin creation
     * stays on storeAdmin() below (its own Gate check + distinct audit
     * action) since granting admin is the one role-grant the Business
     * Rules doc calls out specifically ("Only an existing Admin can
     * create another Admin"); landlord/student creation here is only
     * gated by the route's role:admin middleware + this request's own
     * authorize(), same defense-in-depth level as everywhere else.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $role = RoleEnum::from($request->validated('role'));

        if (in_array($role, [RoleEnum::Admin, RoleEnum::SuperAdmin], true)) {
            Gate::authorize('createAdmin', User::class);
            $user = $this->authService->createAdmin($request->user(), $request->validated(), $role);
        } else {
            $user = $this->authService->createUser($request->user(), $request->validated(), $role);
        }

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /** v0.2: rooms/bookings/favorites counts are a nice-to-have for the admin detail view. */
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->loadCount(['rooms', 'bookings', 'favorites']));
    }

    /**
     * v0.2: no password field accepted here by design - see
     * UpdateUserRequest's rules(). Business Rules: "Admins should not
     * modify passwords directly unless performing an administrative
     * reset," and there is no reset-token flow built yet.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        $before = $user->only(['name', 'email', 'phone', 'role', 'status']);
        $user->update($request->validated());
        $this->auditLog->log($request->user(), 'user.updated', $user, ['before' => $before, 'after' => $request->validated()]);

        return new UserResource($user);
    }

    /** v0.2: soft delete only (User already has SoftDeletes) - self-delete blocked, same as disable. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        $this->auditLog->log($request->user(), 'user.deleted', $user);
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    /**
     * "Admins should not modify passwords directly unless performing an
     * administrative reset" - this endpoint is explicitly that reset
     * path, separate from the user's own ProfileController::update.
     */
    public function disable(Request $request, User $user): UserResource
    {
        Gate::authorize('disable', $user);

        $user->update(['status' => UserStatusEnum::Inactive]);
        $this->auditLog->log($request->user(), 'user.disabled', $user);

        return new UserResource($user);
    }

    public function enable(Request $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        $user->update(['status' => UserStatusEnum::Active]);
        $this->auditLog->log($request->user(), 'user.enabled', $user);

        return new UserResource($user);
    }
}
