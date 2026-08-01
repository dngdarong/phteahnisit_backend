<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterLandlordRequest;
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
