<?php

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('the public room listing uses a consistent paginated envelope', function () {
    Room::factory()->count(3)->create(['status' => 'approved', 'available' => true]);

    $response = $this->getJson('/api/rooms');

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'links' => ['first', 'last', 'prev', 'next'],
        'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
    ]);
});

test('the student booking listing uses the same paginated envelope shape as room search', function () {
    $student = User::factory()->student()->create();
    Booking::factory()->count(2)->create(['student_id' => $student->id]);

    Sanctum::actingAs($student);
    $response = $this->getJson('/api/bookings');

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'links' => ['first', 'last', 'prev', 'next'],
        'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
    ]);
});

test('the admin user listing uses the same paginated envelope shape as room search', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/users');

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'links' => ['first', 'last', 'prev', 'next'],
        'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
    ]);
});

test('requesting a page beyond the last page returns an empty data array, not an error', function () {
    Room::factory()->count(2)->create(['status' => 'approved', 'available' => true]);

    $response = $this->getJson('/api/rooms?page=999');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('requesting a nonexistent booking id returns a consistent 404 shape', function () {
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings/999999/cancel');

    $response->assertNotFound();
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissingPath('exception');
});

test('requesting a nonexistent conversation id returns a consistent 404 shape', function () {
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->getJson('/api/conversations/999999');

    $response->assertNotFound();
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissingPath('exception');
});

test('requesting a nonexistent admin user id returns a consistent 404 shape', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/users/999999');

    $response->assertNotFound();
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissingPath('exception');
});

test('a malformed bearer token is rejected the same way as no token at all', function () {
    $response = $this->withHeader('Authorization', 'Bearer this-is-not-a-real-token')->getJson('/api/auth/me');

    $response->assertUnauthorized();
});

test('a booking belonging to another student cannot be fetched by id via cancel (ownership, not just existence)', function () {
    $booking = Booking::factory()->create();
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson("/api/bookings/{$booking->id}/cancel");

    $response->assertForbidden();
});

test('a conversation this user is not part of returns 403, not 404, so existence is not leaked differently', function () {
    $conversation = Conversation::factory()->create();
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->getJson("/api/conversations/{$conversation->id}");

    $response->assertForbidden();
});
