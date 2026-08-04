<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('room creation reports all missing required fields at once', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $response = $this->postJson('/api/rooms', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['title', 'description', 'price', 'province', 'district', 'address', 'room_type']);
});

test('room creation rejects a zero or negative price', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $response = $this->postJson('/api/rooms', [
        'title' => 'Cozy studio',
        'description' => 'Near the university',
        'price' => 0,
        'province' => 'Phnom Penh',
        'district' => 'Chamkarmon',
        'address' => '123 Street 51',
        'room_type' => 'single',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('price');
});

test('room creation rejects an invalid room_type', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $response = $this->postJson('/api/rooms', [
        'title' => 'Cozy studio',
        'description' => 'Near the university',
        'price' => 100,
        'province' => 'Phnom Penh',
        'district' => 'Chamkarmon',
        'address' => '123 Street 51',
        'room_type' => 'castle',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('room_type');
});

test('room creation rejects an out-of-range latitude/longitude', function () {
    Sanctum::actingAs(User::factory()->landlord()->create());

    $response = $this->postJson('/api/rooms', [
        'title' => 'Cozy studio',
        'description' => 'Near the university',
        'price' => 100,
        'province' => 'Phnom Penh',
        'district' => 'Chamkarmon',
        'address' => '123 Street 51',
        'room_type' => 'single',
        'latitude' => 999,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('latitude');
});

test('room update accepts a partial payload without requiring every field', function () {
    $landlord = User::factory()->landlord()->create();
    $room = \App\Models\Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved']);

    Sanctum::actingAs($landlord);
    $response = $this->putJson("/api/rooms/{$room->id}", ['price' => 250]);

    $response->assertOk();
    $response->assertJsonPath('data.price', 250);
});

test('room search rejects a per_page above the configured maximum', function () {
    $response = $this->getJson('/api/rooms?per_page=51');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('per_page');
});

test('room search rejects price_max below price_min', function () {
    $response = $this->getJson('/api/rooms?price_min=200&price_max=100');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('price_max');
});
