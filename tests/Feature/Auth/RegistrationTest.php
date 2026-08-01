<?php

use App\Models\AuditLog;
use App\Models\User;

test('a student can register', function () {
    $response = $this->postJson('/api/auth/register/student', [
        'name' => 'Ros Piseth',
        'email' => 'piseth@example.com',
        'phone' => '012345678',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'student');
    $this->assertDatabaseHas('users', ['email' => 'piseth@example.com', 'role' => 'student']);
    expect(AuditLog::where('action', 'user.registered')->exists())->toBeTrue();
});

test('a landlord can register', function () {
    $response = $this->postJson('/api/auth/register/landlord', [
        'name' => 'Sok Dara',
        'email' => 'dara@example.com',
        'phone' => '012999999',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'landlord');
});

test('registration never returns the password or remember_token', function () {
    $response = $this->postJson('/api/auth/register/student', [
        'name' => 'Chea Kunthea',
        'email' => 'kunthea@example.com',
        'phone' => '012345679',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertJsonMissingPath('data.password');
    $response->assertJsonMissingPath('data.remember_token');
});

test('registration requires a unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/auth/register/student', [
        'name' => 'Someone',
        'email' => 'taken@example.com',
        'phone' => '012345678',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});

test('registration requires a valid Cambodian-style phone number', function () {
    $response = $this->postJson('/api/auth/register/student', [
        'name' => 'Someone',
        'email' => 'phone-test@example.com',
        'phone' => '12345', // missing leading 0, too short
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('phone');
});
