<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'string', 'email'],
            'password'              => ['required', 'string', 'max:100', 'confirmed', Password::min(8)->numbers()->symbols()->mixedCase()],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
