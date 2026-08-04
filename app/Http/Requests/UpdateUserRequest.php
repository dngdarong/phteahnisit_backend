<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level 'role:admin' middleware already restricts this,
        // this is a second, explicit check per the Security Rules doc
        // ("never trust a single layer").
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * No `password` rule here, intentionally. Business Rules: "Admins
     * should not modify passwords directly unless performing an
     * administrative reset" - there is no reset-token flow built yet,
     * so this endpoint simply never accepts one. A password in the
     * request payload is silently ignored (not present in validated()).
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^0[0-9]{8,9}$/'],
            'role' => ['sometimes', 'required', new Enum(RoleEnum::class)],
            'status' => ['sometimes', 'required', new Enum(UserStatusEnum::class)],
        ];
    }
}
