<?php

namespace App\Http\Requests\OrderLinks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterViaOrderLinkRequest extends FormRequest
{
    /**
     * Mesmas regras do cadastro público (CPF e e-mail únicos) — de propósito sem
     * `registration_ip`: quem se cadastra aqui foi convidado individualmente pelo
     * vendedor, então travar por IP não faz sentido nesse fluxo.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'document' => ['required', 'string', 'size:11', 'unique:users,document'],
            'password' => ['required', 'string', 'max:100', 'confirmed', Password::min(8)->numbers()->symbols()->mixedCase()],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
