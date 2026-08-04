<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function registerStudent(array $data): User
    {
        return $this->register($data, RoleEnum::Student);
    }

    public function registerLandlord(array $data): User
    {
        return $this->register($data, RoleEnum::Landlord);
    }

    /**
     * Admin-only path. Not exposed on any public route - the controller
     * calling this must already be behind role:admin middleware and
     * UserPolicy::createAdmin (Business Rules: "Only an existing Admin
     * can create another Admin").
     */
    public function createAdmin(User $creator, array $data): User
    {
        $admin = $this->register($data, RoleEnum::Admin);

        $this->auditLog->log($creator, 'user.admin_created', $admin);

        return $admin;
    }

    /**
     * v0.2: general-purpose admin-creates-user path (any role except
     * admin - that stays on createAdmin() above, which has its own
     * Gate::authorize('createAdmin', ...) check and distinct
     * 'user.admin_created' audit action since granting admin is more
     * sensitive than creating a landlord/student account).
     */
    public function createUser(User $creator, array $data, RoleEnum $role): User
    {
        $user = $this->register($data, $role);

        $this->auditLog->log($creator, 'user.created_by_admin', $user, ['role' => $role->value]);

        return $user;
    }

    private function register(array $data, RoleEnum $role): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => $role,
            'status' => UserStatusEnum::Active,
        ]);

        $this->auditLog->log($user, 'user.registered', $user, ['role' => $role->value]);

        return $user;
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been disabled.'],
            ]);
        }

        $expiration = config('sanctum.expiration');
        $expiresAt = is_numeric($expiration) && (int) $expiration > 0
            ? now()->addMinutes((int) $expiration)
            : null;

        // Each login creates a new token (Auth doc: Session Rules).
        $token = $user->createToken('api-token', ['*'], $expiresAt)->plainTextToken;

        $this->auditLog->log($user, 'user.login', $user);

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
        $this->auditLog->log($user, 'user.logout', $user);
    }
}
