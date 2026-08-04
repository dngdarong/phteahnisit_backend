<?php

use App\Models\Conversation;
use App\Models\Room;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a student messaging a landlord about a room creates a conversation', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create(['status' => 'approved']);
    $student = User::factory()->student()->create();
    Sanctum::actingAs($student);

    $response = $this->postJson("/api/rooms/{$room->id}/messages", ['body' => 'Is this room still available?']);

    $response->assertCreated();
    $this->assertDatabaseHas('conversations', ['room_id' => $room->id, 'student_id' => $student->id, 'landlord_id' => $landlord->id]);
    $this->assertDatabaseHas('messages', ['sender_id' => $student->id, 'body' => 'Is this room still available?']);
});

test('a second message from the same student about the same room reuses the existing conversation', function () {
    $room = Room::factory()->create(['status' => 'approved']);
    $student = User::factory()->student()->create();
    Sanctum::actingAs($student);

    $this->postJson("/api/rooms/{$room->id}/messages", ['body' => 'First message'])->assertCreated();
    $this->postJson("/api/rooms/{$room->id}/messages", ['body' => 'Second message'])->assertCreated();

    expect(Conversation::where('room_id', $room->id)->where('student_id', $student->id)->count())->toBe(1);
});

test('a landlord cannot start a conversation (only a student can initiate)', function () {
    $room = Room::factory()->create(['status' => 'approved']);
    Sanctum::actingAs(User::factory()->landlord()->create());

    $this->postJson("/api/rooms/{$room->id}/messages", ['body' => 'Hello'])->assertForbidden();
});

test('the two participants can exchange replies within an existing conversation', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create();
    $student = User::factory()->student()->create();
    $conversation = Conversation::factory()->create(['room_id' => $room->id, 'student_id' => $student->id, 'landlord_id' => $landlord->id]);

    Sanctum::actingAs($landlord);
    $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Yes, still available.'])->assertCreated();

    Sanctum::actingAs($student);
    $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Great, I will book it.'])->assertCreated();

    expect($conversation->messages()->count())->toBe(2);
});

test('a user who is not a participant cannot read or write to a conversation', function () {
    $conversation = Conversation::factory()->create();
    Sanctum::actingAs(User::factory()->student()->create());

    $this->getJson("/api/conversations/{$conversation->id}")->assertForbidden();
    $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Snooping'])->assertForbidden();
});

test('an admin cannot read a conversation - chat is participant-only, no admin bypass', function () {
    $conversation = Conversation::factory()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson("/api/conversations/{$conversation->id}")->assertForbidden();
});

test('a user only sees their own conversations in the list', function () {
    $student = User::factory()->student()->create();
    $mine = Conversation::factory()->create(['student_id' => $student->id]);
    Conversation::factory()->create(); // someone else's conversation

    Sanctum::actingAs($student);
    $response = $this->getJson('/api/conversations');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $mine->id);
});

test('opening a conversation marks the other participant\'s messages as read', function () {
    $landlord = User::factory()->landlord()->create();
    $room = Room::factory()->for($landlord, 'landlord')->create();
    $student = User::factory()->student()->create();
    $conversation = Conversation::factory()->create(['room_id' => $room->id, 'student_id' => $student->id, 'landlord_id' => $landlord->id]);

    Sanctum::actingAs($student);
    $this->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Hi there'])->assertCreated();

    Sanctum::actingAs($landlord);
    $this->getJson("/api/conversations/{$conversation->id}")->assertOk();

    $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'sender_id' => $student->id, 'read_at' => null]);
});
