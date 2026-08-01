<?php

use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('public search only returns approved and available rooms', function () {
    Room::factory()->create(['status' => 'approved', 'available' => true]);
    Room::factory()->create(['status' => 'approved', 'available' => false]);
    Room::factory()->pending()->create();
    Room::factory()->rejected()->create();

    $response = $this->getJson('/api/rooms');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('guests can view an approved room\'s detail, including contact info', function () {
    $room = Room::factory()->create(['status' => 'approved']);

    $response = $this->getJson("/api/rooms/{$room->id}");

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['landlord' => ['id', 'name', 'phone']]]);
});

test('a guest cannot view a pending room', function () {
    $room = Room::factory()->pending()->create();

    $this->getJson("/api/rooms/{$room->id}")->assertForbidden();
});

test('a guest cannot view a rejected room', function () {
    $room = Room::factory()->rejected()->create();

    $this->getJson("/api/rooms/{$room->id}")->assertForbidden();
});

test('the owning landlord can view their own pending room', function () {
    $owner = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->pending()->create();

    Sanctum::actingAs($owner);

    $this->getJson("/api/rooms/{$room->id}")->assertOk();
});

test('another landlord cannot view a pending room they do not own', function () {
    $room = Room::factory()->pending()->create();

    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->getJson("/api/rooms/{$room->id}")->assertForbidden();
});

test('an admin can view any room regardless of status', function () {
    $room = Room::factory()->pending()->create();

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson("/api/rooms/{$room->id}")->assertOk();
});

test('an approved but unavailable room is still viewable directly, just excluded from search', function () {
    $room = Room::factory()->create(['status' => 'approved', 'available' => false]);

    $this->getJson("/api/rooms/{$room->id}")->assertOk();
    expect($this->getJson('/api/rooms')->json('data'))->toHaveCount(0);
});

test('search supports keyword, province, and price filters', function () {
    Room::factory()->create(['status' => 'approved', 'available' => true, 'title' => 'Sunny Studio', 'province' => 'Phnom Penh', 'price' => 100]);
    Room::factory()->create(['status' => 'approved', 'available' => true, 'title' => 'Dark Basement', 'province' => 'Siem Reap', 'price' => 50]);

    $this->getJson('/api/rooms?keyword=Sunny')->assertJsonCount(1, 'data');
    $this->getJson('/api/rooms?province=Siem+Reap')->assertJsonCount(1, 'data');
    $this->getJson('/api/rooms?price_min=80')->assertJsonCount(1, 'data');
});

test('a landlord can list only their own rooms across all statuses via /my-rooms', function () {
    $owner = User::factory()->landlord()->create();
    Room::factory()->for($owner, 'landlord')->pending()->create();
    Room::factory()->for($owner, 'landlord')->create(['status' => 'approved']);
    Room::factory()->create(); // someone else's room

    Sanctum::actingAs($owner);

    $response = $this->getJson('/api/my-rooms');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
