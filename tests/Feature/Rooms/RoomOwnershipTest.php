<?php

use App\Enums\RoomStatusEnum;
use App\Models\AuditLog;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a landlord can create a room, which starts pending', function () {
    $landlord = User::factory()->landlord()->create();
    Sanctum::actingAs($landlord);

    $response = $this->postJson('/api/rooms', [
        'title' => 'Cozy room near AEU',
        'description' => 'A nice room.',
        'price' => 120,
        'province' => 'Phnom Penh',
        'district' => 'Chamkar Mon',
        'address' => '123 Street 51',
        'room_type' => 'single_room',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'pending');
    $this->assertDatabaseHas('rooms', ['title' => 'Cozy room near AEU', 'landlord_id' => $landlord->id, 'status' => 'pending']);
    expect(AuditLog::where('action', 'room.created')->exists())->toBeTrue();
});

test('creating a room accepts the string "true"/"false" for available, as sent by multipart form clients', function () {
    // FormData (required for image uploads) always serializes JS booleans
    // as the literal strings "true"/"false" - PHP/Laravel's `boolean` rule
    // only natively accepts 1/0/"1"/"0". Regression test for that gap.
    $landlord = User::factory()->landlord()->create();
    Sanctum::actingAs($landlord);

    $response = $this->post('/api/rooms', [
        'title' => 'Multipart room', 'description' => 'x', 'price' => 50,
        'province' => 'Phnom Penh', 'district' => 'D', 'address' => 'A',
        'room_type' => 'single_room', 'available' => 'false',
    ], ['Accept' => 'application/json']);

    $response->assertCreated();
    $response->assertJsonPath('data.available', false);
});

test('a student cannot create a room', function () {
    Sanctum::actingAs(User::factory()->student()->create());

    $this->postJson('/api/rooms', [
        'title' => 'Nope', 'description' => 'x', 'price' => 10,
        'province' => 'PP', 'district' => 'D', 'address' => 'A', 'room_type' => 'single_room',
    ])->assertForbidden();
});

test('a landlord cannot edit another landlord\'s room', function () {
    $owner = User::factory()->landlord()->create();
    $other = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->create();

    Sanctum::actingAs($other);

    $this->putJson("/api/rooms/{$room->id}", ['title' => 'Hijacked'])->assertForbidden();
    $this->assertDatabaseHas('rooms', ['id' => $room->id, 'title' => $room->title]);
});

test('a landlord cannot delete another landlord\'s room', function () {
    $owner = User::factory()->landlord()->create();
    $other = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->create();

    Sanctum::actingAs($other);

    $this->deleteJson("/api/rooms/{$room->id}")->assertForbidden();
    $this->assertDatabaseHas('rooms', ['id' => $room->id, 'deleted_at' => null]);
});

test('a landlord can edit their own room', function () {
    $owner = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->pending()->create();

    Sanctum::actingAs($owner);

    $response = $this->putJson("/api/rooms/{$room->id}", ['title' => 'Updated title']);

    $response->assertOk();
    $response->assertJsonPath('data.title', 'Updated title');
});

test('an admin can edit or delete any room regardless of ownership', function () {
    $room = Room::factory()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->putJson("/api/rooms/{$room->id}", ['title' => 'Admin edited'])->assertOk();
    $this->deleteJson("/api/rooms/{$room->id}")->assertOk();
    $this->assertSoftDeleted('rooms', ['id' => $room->id]);
});

test('editing an approved room reverts it to pending', function () {
    $owner = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->create(['status' => RoomStatusEnum::Approved]);

    Sanctum::actingAs($owner);

    $response = $this->putJson("/api/rooms/{$room->id}", ['title' => 'Freshly edited']);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'pending');
    $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'pending']);
});

test('editing a pending room does not change its status', function () {
    $owner = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->pending()->create();

    Sanctum::actingAs($owner);

    $response = $this->putJson("/api/rooms/{$room->id}", ['title' => 'Still pending edit']);

    $response->assertJsonPath('data.status', 'pending');
});

test('a landlord deleting their own room only soft-deletes it', function () {
    $owner = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->create();

    Sanctum::actingAs($owner);

    $this->deleteJson("/api/rooms/{$room->id}")->assertOk();
    $this->assertSoftDeleted('rooms', ['id' => $room->id]);
});
