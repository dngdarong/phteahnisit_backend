<?php

use App\Models\AuditLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('an admin can list users, optionally filtered by role', function () {
    User::factory()->landlord()->count(2)->create();
    User::factory()->student()->count(3)->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/users?role=landlord');

    expect($response->json('data'))->toHaveCount(2);
});

test('a non-admin cannot list users', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->getJson('/api/admin/users')->assertForbidden();
});

test('an admin can disable another user, which is audit logged', function () {
    $target = User::factory()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson("/api/admin/users/{$target->id}/disable");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'inactive');
    expect(AuditLog::where('action', 'user.disabled')->exists())->toBeTrue();
});

test('an admin cannot disable their own account', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $this->postJson("/api/admin/users/{$admin->id}/disable")->assertForbidden();
});

test('an admin can re-enable a disabled user', function () {
    $target = User::factory()->inactive()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson("/api/admin/users/{$target->id}/enable");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'active');
});

test('only an admin can create another admin account', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->postJson('/api/admin/users/admins', [
        'name' => 'New Admin', 'email' => 'newadmin@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1',
    ])->assertForbidden();
});

test('an admin creating another admin is audit logged', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson('/api/admin/users/admins', [
        'name' => 'New Admin', 'email' => 'newadmin@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'admin');
    expect(AuditLog::where('action', 'user.admin_created')->exists())->toBeTrue();
});
