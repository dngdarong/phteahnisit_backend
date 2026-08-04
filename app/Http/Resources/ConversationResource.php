<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Conversation
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStudent = $request->user()?->id === $this->student_id;

        return [
            'id' => $this->id,
            // Same null-safety as BookingResource: the room may have been
            // soft-deleted since this conversation was started (the thread
            // is intentionally kept alive - see the conversations migration).
            'room' => $this->whenLoaded('room', fn () => $this->room ? new RoomResource($this->room) : null),
            // The other side of this conversation, relative to whoever is
            // asking. Same null-safety as `room` above: a soft-deleted
            // participant leaves the relation eager-loaded but null.
            'other_participant' => $isStudent
                ? $this->whenLoaded('landlord', fn () => $this->landlord ? new UserResource($this->landlord) : null)
                : $this->whenLoaded('student', fn () => $this->student ? new UserResource($this->student) : null),
            'last_message_at' => $this->last_message_at,
            'unread_count' => $this->when(
                $this->relationLoaded('messages'),
                fn () => $this->messages->where('sender_id', '!=', $request->user()?->id)->whereNull('read_at')->count(),
            ),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
        ];
    }
}
