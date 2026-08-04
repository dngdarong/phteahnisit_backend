<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // A soft-deleted room resolves the relation to null rather than
            // omitting it (it was still eager-loaded) - render that as a
            // clean null instead of a RoomResource full of null fields.
            'room' => $this->whenLoaded('room', fn () => $this->room ? new RoomResource($this->room) : null),
            // Same null-safety as `room` above: a soft-deleted student
            // account still leaves this relation eager-loaded but null.
            'student' => $this->whenLoaded('student', fn () => $this->student ? new UserResource($this->student) : null),
            'move_in_date' => $this->move_in_date->toDateString(),
            'duration_months' => $this->duration_months,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
