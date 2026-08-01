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
            'room' => new RoomResource($this->whenLoaded('room')),
            'student' => new UserResource($this->whenLoaded('student')),
            'move_in_date' => $this->move_in_date->toDateString(),
            'duration_months' => $this->duration_months,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
