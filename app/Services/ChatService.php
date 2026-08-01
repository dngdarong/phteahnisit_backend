<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatService
{
    /**
     * A conversation is implicitly created (or reused) the first time a
     * student messages a landlord about a room - one thread per
     * (room, student) pair, per the unique constraint on `conversations`.
     */
    public function startOrSend(User $student, Room $room, string $body): Message
    {
        return DB::transaction(function () use ($student, $room, $body) {
            $conversation = Conversation::firstOrCreate(
                ['room_id' => $room->id, 'student_id' => $student->id],
                ['landlord_id' => $room->landlord_id],
            );

            return $this->send($student, $conversation, $body);
        });
    }

    public function send(User $sender, Conversation $conversation, string $body): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        return $message;
    }

    /** Marks every message the reader didn't send as read, when they open the thread. */
    public function markRead(User $reader, Conversation $conversation): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
