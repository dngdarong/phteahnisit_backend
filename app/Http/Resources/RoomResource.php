<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Room
 */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'province' => $this->province,
            'district' => $this->district,
            'commune' => $this->commune,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'room_type' => $this->room_type->value,
            'available' => $this->available,
            'status' => $this->status->value,
            'images' => RoomImageResource::collection($this->whenLoaded('images')),
            // Only meaningful (and only loaded) for an authenticated student -
            // see RoomController's conditional eager-load of the scoped relation.
            'is_favorited' => $this->when($this->relationLoaded('favorites'), fn () => $this->favorites->isNotEmpty()),
            // Contact info is intentionally public per FRS Room Detail
            // Module (no auth gate on landlord contact for v0.1).
            'landlord' => $this->whenLoaded('landlord', fn () => [
                'id' => $this->landlord->id,
                'name' => $this->landlord->name,
                'phone' => $this->landlord->phone,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
