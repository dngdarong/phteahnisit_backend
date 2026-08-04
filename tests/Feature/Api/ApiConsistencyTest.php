<?php

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a booking on an otherwise-fine room still reports the student as null instead of a broken object when the student account was deleted', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $student = User::factory()->student()->create();
    $booking = Booking::factory()->for($room)->create(['student_id' => $student->id]);

    $student->delete(); // admin soft-delete path, same as DELETE /api/admin/users/{user}

    Sanctum::actingAs($landlord);
    $response = $this->getJson('/api/landlord/bookings');

    $response->assertOk();
    $response->assertJsonPath('data.0.id', $booking->id);
    $response->assertJsonPath('data.0.student', null);
});

test('a conversation reports the other participant as null instead of a broken object when their account was deleted', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create();
    $student = User::factory()->student()->create();
    $conversation = Conversation::factory()->create([
        'room_id' => $room->id,
        'student_id' => $student->id,
        'landlord_id' => $landlord->id,
    ]);

    $student->delete();

    Sanctum::actingAs($landlord);
    $response = $this->getJson("/api/conversations/{$conversation->id}");

    $response->assertOk();
    $response->assertJsonPath('data.other_participant', null);
});

test('a 404 API response never leaks debug trace/file/line data, even with APP_DEBUG on', function () {
    config(['app.debug' => true]);

    $response = $this->getJson('/api/rooms/999999');

    $response->assertNotFound();
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissingPath('exception');
    $response->assertJsonMissingPath('file');
    $response->assertJsonMissingPath('line');
    $response->assertJsonMissingPath('trace');
});

test('a 405 API response never leaks debug trace/file/line data, even with APP_DEBUG on', function () {
    config(['app.debug' => true]);

    $response = $this->patchJson('/api/rooms', []);

    $response->assertStatus(405);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissingPath('exception');
    $response->assertJsonMissingPath('trace');
});

test('a 422 validation response is unaffected by the debug-stripping response hook', function () {
    config(['app.debug' => true]);

    $response = $this->getJson('/api/rooms?price_min=not-a-number');

    $response->assertStatus(422);
    $response->assertJsonStructure(['message', 'errors' => ['price_min']]);
});

test('non-API routes are left untouched by the debug-stripping response hook', function () {
    config(['app.debug' => true]);

    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound();
    // The web (non-JSON) 404 page is unaffected - just confirm it still renders as HTML, not JSON.
    $response->assertHeader('content-type', 'text/html; charset=UTF-8');
});
