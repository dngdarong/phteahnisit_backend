<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('a user can view their own profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
});

test('a user can update their own name, email, and phone', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/profile', [
        'name' => 'New Name',
        'email' => 'newemail@example.com',
        'phone' => '012000000',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'New Name');
});

test('changing password requires the current password to be correct', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/profile', [
        'current_password' => 'WrongPassword1',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertUnprocessable();
});

test('changing password succeeds with the correct current password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword1')]);
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/profile', [
        'current_password' => 'OldPassword1',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertOk();
    expect(Hash::check('NewPassword1', $user->fresh()->password))->toBeTrue();
});

test('the profile response never leaks the password hash', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/profile')->assertJsonMissingPath('data.password');
});
