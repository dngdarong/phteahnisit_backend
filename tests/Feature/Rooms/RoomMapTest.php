<?php

use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('the map endpoint only returns approved, available rooms with pinned coordinates', function () {
    Room::factory()->create(['status' => 'approved', 'available' => true, 'latitude' => 11.5564, 'longitude' => 104.9282]);
    Room::factory()->create(['status' => 'approved', 'available' => true, 'latitude' => null, 'longitude' => null]);
    Room::factory()->create(['status' => 'approved', 'available' => false, 'latitude' => 11.5, 'longitude' => 104.9]);
    Room::factory()->pending()->create(['latitude' => 11.5, 'longitude' => 104.9]);

    $response = $this->getJson('/api/rooms/map');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('a landlord can set a room\'s coordinates when creating it', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $response = $this->postJson('/api/rooms', [
        'title' => 'Room with a pin', 'description' => 'x', 'price' => 100,
        'province' => 'Phnom Penh', 'district' => 'Chamkar Mon', 'address' => 'A',
        'room_type' => 'single_room', 'latitude' => 11.5564, 'longitude' => 104.9282,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.latitude', 11.5564);
    $response->assertJsonPath('data.longitude', 104.9282);
});
