<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Broad check only - conversation participancy (the real
        // authorization boundary) needs the resolved Room/Conversation
        // instance, checked via Gate::authorize in the controller, same
        // pattern as UpdateRoomRequest.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
