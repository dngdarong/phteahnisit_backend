<?php

namespace App\Http\Requests;

use App\Enums\RoomTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level 'role:landlord' middleware already restricts this,
        // this is a second, explicit check per the Security Rules doc
        // ("never trust a single layer").
        return $this->user()?->isLandlord() ?? false;
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'province' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'room_type' => ['required', new Enum(RoomTypeEnum::class)],
            'available' => ['boolean'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpeg,png,webp', 'max:' . config('phteahnisit.max_image_kb', 5120)],
        ];
    }
}
