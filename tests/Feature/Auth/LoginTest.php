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
