<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level 'role:admin' middleware already restricts this,
        // this is a second, explicit check per the Security Rules doc
        // ("never trust a single layer").
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,9}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', new Enum(RoleEnum::class)],
        ];
    }
}
