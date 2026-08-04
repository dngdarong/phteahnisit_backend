<?php

use App\Enums\UserStatusEnum;
use App\Models\AuditLog;
use App\Models\User;

test('a user can log in with correct credentials and receives a token', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['token', 'user']);
    expect(AuditLog::where('action', 'user.login')->where('actor_id', $user->id)->exists())->toBeTrue();
});

test('login attempts are rate limited by email and IP', function () {
    config([
        'phteahnisit.auth.login.max_attempts' => 1,
        'phteahnisit.auth.login.decay_minutes' => 1,
    ]);

    $userA = User::factory()->create([
        'email' => 'login-limit-a@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $userB = User::factory()->create([
        'email' => 'login-limit-b@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $userA->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $this->postJson('/api/auth/login', [
        'email' => $userA->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();

    $this->postJson('/api/auth/login', [
        'email' => $userB->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

test('successful login clears the login limiter', function () {
    config([
        'phteahnisit.auth.login.max_attempts' => 2,
        'phteahnisit.auth.login.decay_minutes' => 1,
    ]);

    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertOk();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

test('login fails with the wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();
});

test('login fails for a disabled account', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
        'status' => UserStatusEnum::Inactive,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertUnprocessable();
});

test('each login issues a brand new token rather than reusing one', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $first = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])->json('token');
    $second = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])->json('token');

    expect($first)->not->toBe($second);
    expect($user->tokens()->count())->toBe(2);
});

test('a logged in user can log out, which deletes only the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $user->createToken('another-device'); // simulate a second active session

    $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/auth/logout');

    $response->assertOk();
    expect($user->tokens()->count())->toBe(1);
    expect(AuditLog::where('action', 'user.logout')->exists())->toBeTrue();
});

test('a disabled user is rejected on every subsequent request, not just login', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $user->update(['status' => UserStatusEnum::Inactive]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me');

    $response->assertForbidden();
    expect($user->tokens()->count())->toBe(0); // token deleted server-side
});

test('a guest cannot access authenticated endpoints', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});
