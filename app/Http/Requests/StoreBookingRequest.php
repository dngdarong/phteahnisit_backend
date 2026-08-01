<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level 'role:student' middleware already restricts this,
        // this is a second, explicit check per the Security Rules doc
        // ("never trust a single layer") - same pattern as StoreRoomRequest.
        return $this->user()?->isStudent() ?? false;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'move_in_date' => ['required', 'date', 'after_or_equal:today'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:36'],
        ];
    }
}
