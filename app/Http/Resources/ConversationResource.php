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
            'room' => new RoomResource($this->whenLoaded('room')),
            // The other side of this conversation, relative to whoever is asking.
            'other_participant' => new UserResource($isStudent ? $this->whenLoaded('landlord') : $this->whenLoaded('student')),
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
