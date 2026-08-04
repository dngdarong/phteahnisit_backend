<?php

use App\Models\Conversation;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('sending a message rejects an empty body', function () {
    $landlord = User::factory()->landlord()->create();
    $student = User::factory()->student()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create();
    $conversation = Conversation::factory()->create([
        'room_id' => $room->id,
        'student_id' => $student->id,
        'landlord_id' => $landlord->id,
    ]);

    Sanctum::actingAs($student);
    $response = $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => '']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('body');
});

test('sending a message rejects a body over 2000 characters', function () {
    $landlord = User::factory()->landlord()->create();
    $student = User::factory()->student()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create();
    $conversation = Conversation::factory()->create([
        'room_id' => $room->id,
        'student_id' => $student->id,
        'landlord_id' => $landlord->id,
    ]);

    Sanctum::actingAs($student);
    $response = $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => str_repeat('a', 2001)]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('body');
});

test('a user updating their own profile can keep their existing email without a uniqueness conflict', function () {
    $user = User::factory()->create(['email' => 'same@example.com']);

    Sanctum::actingAs($user);
    $response = $this->putJson('/api/profile', ['email' => 'same@example.com']);

    $response->assertOk();
});

test('a user cannot update their profile email to one already taken by another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    Sanctum::actingAs($user);
    $response = $this->putJson('/api/profile', ['email' => 'taken@example.com']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});

test('changing password without confirming it is rejected', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1')]);

    Sanctum::actingAs($user);
    $response = $this->putJson('/api/profile', [
        'current_password' => 'OldPassword1',
        'password' => 'NewPassword1',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('password');
});
