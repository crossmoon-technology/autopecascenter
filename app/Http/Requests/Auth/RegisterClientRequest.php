<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterClientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // O documento é único por papel, não globalmente — quem já é vendedor pode
            // se cadastrar aqui como cliente com o mesmo CPF (ver User::linkToSeller()).
            'document' => ['required', 'string', 'size:11', Rule::unique('users', 'document')->where('role', Role::Client->value)],
            'referral_code' => ['required', 'string', Rule::exists('users', 'referral_code')->where('role', Role::Seller->value)],
            'password' => ['required', 'string', 'max:100', 'confirmed', Password::min(8)->numbers()->symbols()->mixedCase()],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'referral_code.exists' => 'Código de vendedor inválido.',
        ];
    }

    public function seller(): User
    {
        return User::query()
            ->where('referral_code', $this->validated('referral_code'))
            ->where('role', Role::Seller)
            ->firstOrFail();
    }
}
