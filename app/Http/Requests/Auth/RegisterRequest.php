<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * O IP não é um campo do formulário — é anexado aqui pra poder usar a mesma
     * infraestrutura de validação `unique` já usada em email/document, em vez de checar
     * manualmente no service. Não tem input visível pra ele, então o erro correspondente
     * é mostrado como um alerta genérico na view, não perto de um campo.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'registration_ip' => $this->ip(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'document' => ['required', 'string', 'size:11', 'unique:users,document'],
            'registration_ip' => ['required', 'ip', 'unique:users,registration_ip'],
            'password' => ['required', 'string', 'max:100', 'confirmed', Password::min(8)->numbers()->symbols()->mixedCase()],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_ip.unique' => 'Não foi possível concluir o cadastro a partir deste endereço.',
        ];
    }
}
