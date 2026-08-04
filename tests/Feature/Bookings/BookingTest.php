<?php

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a student can book an approved and available room', function () {
    $student = User::factory()->student()->create();
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs($student);

    $response = $this->postJson('/api/bookings', [
        'room_id' => $room->id,
        'move_in_date' => now()->addWeek()->toDateString(),
        'duration_months' => 6,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'pending');
    $this->assertDatabaseHas('bookings', ['room_id' => $room->id, 'student_id' => $student->id, 'status' => 'pending']);
    expect(AuditLog::where('action', 'booking.created')->exists())->toBeTrue();
});

test('a student cannot book a pending (not yet approved) room', function () {
    $room = Room::factory()->pending()->create();
    Sanctum::actingAs(User::factory()->student()->create());

    $response = $this->postJson('/api/bookings', [
        'room_id' => $room->id,
        'move_in_date' => now()->addWeek()->toDateString(),
        'duration_months' => 3,
    ]);

    $response->assertUnprocessable();
});

test('a student cannot book an approved but unavailable room', function () {
    $room = Room::factory()->create(['status' => 'approved', 'available' => false]);
    Sanctum::actingAs(User::factory()->student()->create());

    $this->postJson('/api/bookings', [
        'room_id' => $room->id,
        'move_in_date' => now()->addWeek()->toDateString(),
        'duration_months' => 3,
    ])->assertUnprocessable();
});

test('a student cannot have two pending bookings on the same room at once', function () {
    $student = User::factory()->student()->create();
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs($student);

    $payload = ['room_id' => $room->id, 'move_in_date' => now()->addWeek()->toDateString(), 'duration_months' => 3];

    $this->postJson('/api/bookings', $payload)->assertCreated();
    $this->postJson('/api/bookings', $payload)->assertUnprocessable();
    expect(Booking::where('room_id', $room->id)->where('student_id', $student->id)->count())->toBe(1);
});

test('a student can hold pending bookings on two different rooms at once', function () {
    $student = User::factory()->student()->create();
    $roomA = Room::factory()->create(['status' => 'approved', 'available' => true]);
    $roomB = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs($student);

    $this->postJson('/api/bookings', ['room_id' => $roomA->id, 'move_in_date' => now()->addWeek()->toDateString(), 'duration_months' => 3])->assertCreated();
    $this->postJson('/api/bookings', ['room_id' => $roomB->id, 'move_in_date' => now()->addWeek()->toDateString(), 'duration_months' => 3])->assertCreated();

    expect(Booking::where('student_id', $student->id)->count())->toBe(2);
});

test('a landlord can approve a pending booking on their own room', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->create();

    Sanctum::actingAs($landlord);
    $response = $this->postJson("/api/landlord/bookings/{$booking->id}/approve");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'approved');
    expect(AuditLog::where('action', 'booking.approved')->exists())->toBeTrue();
});

test('a landlord can reject a pending booking on their own room', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->create();

    Sanctum::actingAs($landlord);
    $response = $this->postJson("/api/landlord/bookings/{$booking->id}/reject", ['reason' => 'Room no longer available']);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'rejected');
    $log = AuditLog::where('action', 'booking.rejected')->first();
    expect($log->metadata['reason'])->toBe('Room no longer available');
});

test('a landlord cannot approve or reject a booking on another landlord\'s room', function () {
    $owner = User::factory()->landlord()->create();
    $other = User::factory()->landlord()->create();
    $room = Room::factory()->for($owner, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->create();

    Sanctum::actingAs($other);

    $this->postJson("/api/landlord/bookings/{$booking->id}/approve")->assertForbidden();
    $this->postJson("/api/landlord/bookings/{$booking->id}/reject")->assertForbidden();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'pending']);
});

test('a student can cancel their own pending booking', function () {
    $student = User::factory()->student()->create();
    $booking = Booking::factory()->create(['student_id' => $student->id]);

    Sanctum::actingAs($student);
    $response = $this->postJson("/api/bookings/{$booking->id}/cancel");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'cancelled');
    expect(AuditLog::where('action', 'booking.cancelled')->exists())->toBeTrue();
});

test('a student cannot cancel another student\'s booking', function () {
    $booking = Booking::factory()->create();
    Sanctum::actingAs(User::factory()->student()->create());

    $this->postJson("/api/bookings/{$booking->id}/cancel")->assertForbidden();
});

test('a student cannot cancel a booking that is no longer pending', function () {
    $student = User::factory()->student()->create();
    $booking = Booking::factory()->approved()->create(['student_id' => $student->id]);

    Sanctum::actingAs($student);
    $this->postJson("/api/bookings/{$booking->id}/cancel")->assertForbidden();
});

test('editing an approved room back to pending does not touch its existing bookings', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved']);
    $booking = Booking::factory()->for($room)->approved()->create();

    Sanctum::actingAs($landlord);
    $this->putJson("/api/rooms/{$room->id}", ['title' => 'Refreshed listing'])->assertOk();

    $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'pending']);
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'approved']);
});

test('a student cannot access the landlord booking review endpoints', function () {
    Sanctum::actingAs(User::factory()->student()->create());

    $this->getJson('/api/landlord/bookings')->assertForbidden();
});

test('a landlord cannot approve a booking that is already approved', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->approved()->create();

    Sanctum::actingAs($landlord);
    $response = $this->postJson("/api/landlord/bookings/{$booking->id}/approve");

    $response->assertUnprocessable();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'approved']);
});

test('a landlord cannot approve a booking that was rejected', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->rejected()->create();

    Sanctum::actingAs($landlord);
    $this->postJson("/api/landlord/bookings/{$booking->id}/approve")->assertUnprocessable();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'rejected']);
});

test('a landlord cannot approve a booking that was cancelled', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->cancelled()->create();

    Sanctum::actingAs($landlord);
    $this->postJson("/api/landlord/bookings/{$booking->id}/approve")->assertUnprocessable();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
});

test('a landlord cannot reject a booking that is already approved', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->approved()->create();

    Sanctum::actingAs($landlord);
    $this->postJson("/api/landlord/bookings/{$booking->id}/reject")->assertUnprocessable();
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'approved']);
});

test('a landlord cannot reject a booking that was already rejected', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->rejected()->create();

    Sanctum::actingAs($landlord);
    $this->postJson("/api/landlord/bookings/{$booking->id}/reject")->assertUnprocessable();
});

test('double-approving the same pending booking back to back only the first succeeds', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->create();

    Sanctum::actingAs($landlord);

    $first = $this->postJson("/api/landlord/bookings/{$booking->id}/approve");
    $second = $this->postJson("/api/landlord/bookings/{$booking->id}/approve");

    $first->assertOk();
    $second->assertUnprocessable();
    expect(AuditLog::where('action', 'booking.approved')->count())->toBe(1);
});

test('an invalid approve attempt does not write an audit log entry', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved', 'available' => true]);
    $booking = Booking::factory()->for($room)->rejected()->create();

    Sanctum::actingAs($landlord);
    $this->postJson("/api/landlord/bookings/{$booking->id}/approve")->assertUnprocessable();

    expect(AuditLog::where('subject_id', $booking->id)->where('action', 'booking.approved')->exists())->toBeFalse();
});

test('a student cannot create a duplicate pending booking when submitting the same request twice back to back', function () {
    $student = User::factory()->student()->create();
    $room = Room::factory()->create(['status' => 'approved', 'available' => true]);
    Sanctum::actingAs($student);

    $payload = ['room_id' => $room->id, 'move_in_date' => now()->addWeek()->toDateString(), 'duration_months' => 3];

    $first = $this->postJson('/api/bookings', $payload);
    $second = $this->postJson('/api/bookings', $payload);

    $first->assertCreated();
    $second->assertUnprocessable();
    expect(Booking::where('room_id', $room->id)->where('student_id', $student->id)->count())->toBe(1);
});
