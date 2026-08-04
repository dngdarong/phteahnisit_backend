<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterLandlordRequest;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\AuthRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function registerStudent(RegisterStudentRequest $request): JsonResponse
    {
        $key = AuthRateLimiter::registerCacheKey($request);
        $user = $this->authService->registerStudent($request->validated());
        RateLimiter::clear($key);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function registerLandlord(RegisterLandlordRequest $request): JsonResponse
    {
        $key = AuthRateLimiter::registerCacheKey($request);
        $user = $this->authService->registerLandlord($request->validated());
        RateLimiter::clear($key);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = AuthRateLimiter::loginCacheKey($request);

        try {
            $result = $this->authService->login(
                $request->validated('email'),
                $request->validated('password'),
            );
        } catch (ValidationException $exception) {
            Log::channel('security')->warning('Authentication failed.', [
                'event' => 'auth.login.failed',
                'email_hash' => hash('sha256', (string) $request->validated('email')),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => $this->loginFailureReason($exception),
            ]);

            throw $exception;
        }

        RateLimiter::clear($key);
        Log::channel('security')->info('Authentication succeeded.', [
            'event' => 'auth.login.succeeded',
            'user_id' => $result['user']->id,
            'role' => $result['user']->role->value,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        $this->authService->logout($request->user());
        Log::channel('security')->info('Authentication token revoked.', [
            'event' => 'auth.logout',
            'user_id' => $request->user()?->id,
            'token_id' => $token?->id,
            'token_name' => $token?->name,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function loginFailureReason(ValidationException $exception): string
    {
        $message = $exception->errors()['email'][0] ?? 'invalid_credentials';

        return str_contains($message, 'disabled') ? 'inactive_account' : 'invalid_credentials';
    }
}
