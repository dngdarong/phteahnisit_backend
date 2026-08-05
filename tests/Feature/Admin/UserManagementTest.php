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

test('a super admin creating another admin is audit logged', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $response = $this->postJson('/api/admin/users/admins', [
        'name' => 'New Admin', 'email' => 'newadmin@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'admin');
    expect(AuditLog::where('action', 'user.admin_created')->exists())->toBeTrue();
});

test('a regular admin (not super admin) cannot create another admin account', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/admin/users/admins', [
        'name' => 'New Admin', 'email' => 'blockedadmin@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1',
    ])->assertForbidden();
});

// v0.2 - general create/view/update/delete

test('an admin can create a landlord via the general endpoint, audit logged', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson('/api/admin/users', [
        'name' => 'New Landlord', 'email' => 'newlandlord@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'role' => 'landlord',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'landlord');
    expect(AuditLog::where('action', 'user.created_by_admin')->exists())->toBeTrue();
});

test('an admin can create a student via the general endpoint', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson('/api/admin/users', [
        'name' => 'New Student', 'email' => 'newstudent@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'role' => 'student',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'student');
});

test('creating an admin via the general endpoint uses the createAdmin gate and audit action', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $response = $this->postJson('/api/admin/users', [
        'name' => 'New Admin', 'email' => 'anotheradmin@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'role' => 'admin',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'admin');
    expect(AuditLog::where('action', 'user.admin_created')->exists())->toBeTrue();
});

test('a regular admin cannot create an admin via the general endpoint', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/admin/users', [
        'name' => 'New Admin', 'email' => 'blockedgeneral@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'role' => 'admin',
    ])->assertForbidden();
});

test('a super admin can create another super admin via the general endpoint', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $response = $this->postJson('/api/admin/users', [
        'name' => 'New Super Admin', 'email' => 'newsuperadmin@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'role' => 'super_admin',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.role', 'super_admin');
});

test('a non-admin cannot create a user via the general endpoint', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->postJson('/api/admin/users', [
        'name' => 'New Student', 'email' => 'blocked@example.com', 'phone' => '012345678',
        'password' => 'Password1', 'password_confirmation' => 'Password1', 'role' => 'student',
    ])->assertForbidden();
});

test('an admin can view a single user with rooms/bookings/favorites counts', function () {
    $target = User::factory()->landlord()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson("/api/admin/users/{$target->id}");

    $response->assertOk();
    $response->assertJsonPath('data.id', $target->id);
    $response->assertJsonStructure(['data' => ['rooms_count', 'bookings_count', 'favorites_count']]);
});

test('a non-admin cannot view a single user', function () {
    $target = User::factory()->create();
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->getJson("/api/admin/users/{$target->id}")->assertForbidden();
});

test('an admin can update another user\'s fields, audit logged', function () {
    $target = User::factory()->student()->create(['name' => 'Old Name']);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->putJson("/api/admin/users/{$target->id}", ['name' => 'New Name']);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'New Name');
    expect(AuditLog::where('action', 'user.updated')->exists())->toBeTrue();
});

test('sending a password on the update endpoint is silently ignored, not applied', function () {
    $target = User::factory()->student()->create();
    $originalHash = $target->password;
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->putJson("/api/admin/users/{$target->id}", [
        'name' => $target->name,
        'password' => 'BrandNewPassword1',
    ]);

    $response->assertOk();
    expect($target->fresh()->password)->toBe($originalHash);
});

test('a non-admin cannot update a user', function () {
    $target = User::factory()->create();
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->putJson("/api/admin/users/{$target->id}", ['name' => 'Hacked'])->assertForbidden();
});

test('an admin can soft delete another user, audit logged', function () {
    $target = User::factory()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->deleteJson("/api/admin/users/{$target->id}");

    $response->assertOk();
    expect(User::find($target->id))->toBeNull(); // excluded by default scope
    expect(User::withTrashed()->find($target->id))->not->toBeNull(); // still in the DB
    expect(AuditLog::where('action', 'user.deleted')->exists())->toBeTrue();
});

test('an admin cannot delete their own account', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $this->deleteJson("/api/admin/users/{$admin->id}")->assertForbidden();
    expect(User::find($admin->id))->not->toBeNull();
});

test('a non-admin cannot delete a user', function () {
    $target = User::factory()->create();
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->deleteJson("/api/admin/users/{$target->id}")->assertForbidden();
});

// Super Admin vs. Admin - admin-tier targets (admin/super_admin) can only
// be managed by a Super Admin; a regular Admin can still manage landlord/
// student accounts normally.

test('a regular admin cannot update another admin\'s fields', function () {
    $target = User::factory()->admin()->create(['name' => 'Old Name']);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->putJson("/api/admin/users/{$target->id}", ['name' => 'New Name'])->assertForbidden();
});

test('a super admin can update another admin\'s fields', function () {
    $target = User::factory()->admin()->create(['name' => 'Old Name']);
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $response = $this->putJson("/api/admin/users/{$target->id}", ['name' => 'New Name']);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'New Name');
});

test('a regular admin cannot disable another admin', function () {
    $target = User::factory()->admin()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/users/{$target->id}/disable")->assertForbidden();
});

test('a super admin can disable a regular admin', function () {
    $target = User::factory()->admin()->create();
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->postJson("/api/admin/users/{$target->id}/disable")->assertOk();
});

test('a regular admin cannot delete another admin', function () {
    $target = User::factory()->admin()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->deleteJson("/api/admin/users/{$target->id}")->assertForbidden();
    expect(User::find($target->id))->not->toBeNull();
});

test('a super admin can delete a regular admin', function () {
    $target = User::factory()->admin()->create();
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->deleteJson("/api/admin/users/{$target->id}")->assertOk();
    expect(User::find($target->id))->toBeNull();
});

test('a super admin cannot disable or delete their own account', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson("/api/admin/users/{$superAdmin->id}/disable")->assertForbidden();
    $this->deleteJson("/api/admin/users/{$superAdmin->id}")->assertForbidden();
});

test('a super admin can access admin-only routes alongside a regular admin', function () {
    User::factory()->landlord()->count(2)->create();
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->getJson('/api/admin/users')->assertOk();
    $this->getJson('/api/admin/rooms/pending')->assertOk();
});
