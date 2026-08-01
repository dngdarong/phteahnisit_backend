<?php

use App\Models\AuditLog;
use App\Models\Favorite;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a student can favorite a room, then unfavorite it by toggling again', function () {
    $student = User::factory()->student()->create();
    $room = Room::factory()->create(['status' => 'approved']);
    Sanctum::actingAs($student);

    $response = $this->postJson("/api/rooms/{$room->id}/favorite");
    $response->assertOk();
    $response->assertJsonPath('is_favorited', true);
    $this->assertDatabaseHas('favorites', ['user_id' => $student->id, 'room_id' => $room->id]);
    expect(AuditLog::where('action', 'favorite.added')->exists())->toBeTrue();

    $response = $this->postJson("/api/rooms/{$room->id}/favorite");
    $response->assertOk();
    $response->assertJsonPath('is_favorited', false);
    $this->assertDatabaseMissing('favorites', ['user_id' => $student->id, 'room_id' => $room->id]);
    expect(AuditLog::where('action', 'favorite.removed')->exists())->toBeTrue();
});

test('a landlord cannot favorite a room', function () {
    $room = Room::factory()->create(['status' => 'approved']);
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->postJson("/api/rooms/{$room->id}/favorite")->assertForbidden();
});

test('an admin cannot favorite a room', function () {
    $room = Room::factory()->create(['status' => 'approved']);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/rooms/{$room->id}/favorite")->assertForbidden();
});

test('a guest cannot favorite a room', function () {
    $room = Room::factory()->create(['status' => 'approved']);

    $this->postJson("/api/rooms/{$room->id}/favorite")->assertUnauthorized();
});

test('a student only sees their own saved rooms in the favorites list', function () {
    $student = User::factory()->student()->create();
    $roomA = Room::factory()->create(['status' => 'approved']);
    $roomB = Room::factory()->create(['status' => 'approved']);
    Favorite::factory()->create(['user_id' => $student->id, 'room_id' => $roomA->id]);
    Favorite::factory()->create(['room_id' => $roomB->id]); // someone else's favorite

    Sanctum::actingAs($student);
    $response = $this->getJson('/api/favorites');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $roomA->id);
});

test('room listings show is_favorited for a student, based on their own favorites only', function () {
    $student = User::factory()->student()->create();
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Favorite::factory()->create(['user_id' => $student->id, 'room_id' => $room->id]);

    Sanctum::actingAs($student);
    $response = $this->getJson('/api/rooms');

    $response->assertOk();
    $response->assertJsonPath('data.0.is_favorited', true);
});
