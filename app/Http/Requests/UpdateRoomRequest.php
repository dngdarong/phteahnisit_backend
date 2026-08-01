<?php

namespace App\Http\Requests;

use App\Enums\RoomTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Broad role gate only - landlord or admin, per the permission
        // matrix (docs/BACKEND_ARCHITECTURE.md #5: Edit room - landlord
        // (own) or admin (any)). Ownership itself is checked via
        // RoomPolicy::update in the controller (needs the route-bound
        // Room instance, not available here).
        $user = $this->user();

        return $user && ($user->isLandlord() || $user->isAdmin());
    }

    protected function prepareForValidation(): void
    {
        // multipart/form-data (required for the image upload fields)
        // always serializes booleans as the literal strings "true"/"false",
        // which Laravel's `boolean` rule does not accept (only 1/0/"1"/"0").
        if ($this->has('available')) {
            $this->merge(['available' => $this->boolean('available')]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'province' => ['sometimes', 'required', 'string', 'max:100'],
            'district' => ['sometimes', 'required', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'room_type' => ['sometimes', 'required', new Enum(RoomTypeEnum::class)],
            'available' => ['sometimes', 'boolean'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpeg,png,webp', 'max:' . config('phteahnisit.max_image_kb', 5120)],
        ];
    }
}
