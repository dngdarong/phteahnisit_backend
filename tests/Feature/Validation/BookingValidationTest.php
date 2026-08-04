<?php

use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('booking creation rejects a duration under 1 month', function () {
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings', [
        'room_id' => $room->id,
        'move_in_date' => now()->addWeek()->toDateString(),
        'duration_months' => 0,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('duration_months');
});

test('booking creation rejects a duration over 36 months', function () {
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings', [
        'room_id' => $room->id,
        'move_in_date' => now()->addWeek()->toDateString(),
        'duration_months' => 37,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('duration_months');
});

test('booking creation accepts the 36-month boundary', function () {
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings', [
        'room_id' => $room->id,
        'move_in_date' => now()->addWeek()->toDateString(),
        'duration_months' => 36,
    ]);

    $response->assertCreated();
});

test('booking creation rejects a move-in date in the past', function () {
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings', [
        'room_id' => $room->id,
        'move_in_date' => now()->subDay()->toDateString(),
        'duration_months' => 3,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('move_in_date');
});

test('booking creation rejects a room_id that does not exist', function () {
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings', [
        'room_id' => 999999,
        'move_in_date' => now()->addWeek()->toDateString(),
        'duration_months' => 3,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('room_id');
});

test('booking creation reports all missing required fields at once', function () {
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['room_id', 'move_in_date', 'duration_months']);
});
