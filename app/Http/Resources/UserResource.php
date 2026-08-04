<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            // password, remember_token intentionally omitted -
            // never returned in API responses (Security Rules).

            // v0.2: only present when the controller eager-loads counts
            // (the admin user-detail view) - whenCounted() omits the key
            // entirely otherwise, so the list endpoint stays lightweight.
            'rooms_count' => $this->whenCounted('rooms'),
            'bookings_count' => $this->whenCounted('bookings'),
            'favorites_count' => $this->whenCounted('favorites'),
        ];
    }
}
