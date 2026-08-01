<?php

namespace App\Http\Requests;

use App\Enums\RoomTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SearchRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'room_type' => ['nullable', new Enum(RoomTypeEnum::class)],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'gte:price_min'],
            'sort' => ['nullable', 'in:price_asc,price_desc,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
