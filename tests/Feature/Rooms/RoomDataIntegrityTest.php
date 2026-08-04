<?php

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a booking on a soft-deleted room reports the room as null instead of a broken object', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $student = User::factory()->student()->create();
    $booking = Booking::factory()->for($room)->create(['student_id' => $student->id]);

    $room->delete(); // soft delete, same path as DELETE /api/rooms/{room}

    Sanctum::actingAs($student);
    $response = $this->getJson('/api/bookings');

    $response->assertOk();
    $response->assertJsonPath('data.0.id', $booking->id);
    $response->assertJsonPath('data.0.room', null);
});

test('a conversation about a soft-deleted room survives and reports the room as null', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create();
    $student = User::factory()->student()->create();
    $conversation = Conversation::factory()->create([
        'room_id' => $room->id,
        'student_id' => $student->id,
        'landlord_id' => $landlord->id,
    ]);

    $room->delete();

    Sanctum::actingAs($student);
    $response = $this->getJson("/api/conversations/{$conversation->id}");

    $response->assertOk();
    $response->assertJsonPath('data.room', null);
});

test('viewing a room whose landlord account was deleted reports the landlord as null, not a broken object', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved']);

    $landlord->delete(); // admin soft-delete path, same as DELETE /api/admin/users/{user}

    $response = $this->getJson("/api/rooms/{$room->id}");

    $response->assertOk();
    $response->assertJsonPath('data.landlord', null);
});

test('a favorite on a soft-deleted room is silently excluded from the favorites list, not shown broken', function () {
    $student = User::factory()->student()->create();
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Favorite::factory()->create(['user_id' => $student->id, 'room_id' => $room->id]);

    $room->delete();

    Sanctum::actingAs($student);
    $response = $this->getJson('/api/favorites');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});

test('a room whose landlord account was soft-deleted remains searchable (documented existing behavior, unchanged)', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $landlord->delete();

    $response = $this->getJson('/api/rooms');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('a room can be soft-deleted while it still has a pending booking, and the booking is left untouched', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->create();

    Sanctum::actingAs($landlord);
    $this->deleteJson("/api/rooms/{$room->id}")->assertOk();

    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => BookingStatusEnum::Pending->value]);
});

test('a landlord loses visibility of a pending booking once they delete its room (documented existing behavior, unchanged)', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->create();

    Sanctum::actingAs($landlord);
    $this->deleteJson("/api/rooms/{$room->id}")->assertOk();

    $response = $this->getJson('/api/landlord/bookings');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});

test('a student can still self-cancel a pending booking after its room is deleted', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $student = User::factory()->student()->create();
    $booking = Booking::factory()->for($room)->create(['student_id' => $student->id]);

    $room->delete();

    Sanctum::actingAs($student);
    $response = $this->postJson("/api/bookings/{$booking->id}/cancel");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'cancelled');
});

test('force-deleting a room cascades to remove its bookings, favorites, and conversations', function () {
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->create();
    $favorite = Favorite::factory()->create(['room_id' => $room->id]);
    $conversation = Conversation::factory()->create(['room_id' => $room->id, 'landlord_id' => $room->landlord_id]);

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->deleteJson("/api/admin/rooms/{$room->id}/force")->assertOk();

    $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
});
