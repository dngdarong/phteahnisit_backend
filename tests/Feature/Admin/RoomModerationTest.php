<?php

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('an admin can approve a pending room', function () {
    $room = Room::factory()->pending()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson("/api/admin/rooms/{$room->id}/approve");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'approved');
    expect(AuditLog::where('action', 'room.approved')->exists())->toBeTrue();
});

test('an admin can reject a pending room with a reason', function () {
    $room = Room::factory()->pending()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson("/api/admin/rooms/{$room->id}/reject", ['reason' => 'Blurry photos']);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'rejected');
    $log = AuditLog::where('action', 'room.rejected')->first();
    expect($log->metadata['reason'])->toBe('Blurry photos');
});

test('a landlord cannot approve or reject rooms, including their own', function () {
    $owner = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->pending()->create();
    Sanctum::actingAs($owner);

    $this->postJson("/api/admin/rooms/{$room->id}/approve")->assertForbidden();
});

test('a student cannot access any admin room endpoint', function () {
    $room = Room::factory()->pending()->create();
    Sanctum::actingAs(User::factory()->student()->create());

    $this->getJson('/api/admin/rooms/pending')->assertForbidden();
    $this->postJson("/api/admin/rooms/{$room->id}/approve")->assertForbidden();
});

test('a guest cannot access any admin room endpoint', function () {
    $this->getJson('/api/admin/rooms/pending')->assertUnauthorized();
});

test('only an admin can permanently force-delete a room', function () {
    $room = Room::factory()->create();

    Sanctum::actingAs(User::factory()->landlord()->create());
    $this->deleteJson("/api/admin/rooms/{$room->id}/force")->assertForbidden();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->deleteJson("/api/admin/rooms/{$room->id}/force")->assertOk();
    $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
});

test('the pending queue only lists pending rooms', function () {
    Room::factory()->pending()->count(2)->create();
    Room::factory()->create(['status' => 'approved']);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/rooms/pending');

    expect($response->json('data'))->toHaveCount(2);
});

test('an admin can search rooms by a partial, case-insensitive owner name match', function () {
    $alice = User::factory()->landlord()->create(['name' => 'Alice Wonderland']);
    $bob = User::factory()->landlord()->create(['name' => 'Bob Builder']);
    Room::factory()->for($alice, 'landlord')->create();
    Room::factory()->for($bob, 'landlord')->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/rooms?search=wonder');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.landlord.name'))->toBe('Alice Wonderland');
});
