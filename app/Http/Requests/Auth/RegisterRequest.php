<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            // Um e-mail já usado por uma conta Role::Client é permitido de propósito —
            // vira upgrade da mesma conta pra Seller em vez de rejeitar (ver
            // AuthService::registerSeller()). Só bloqueia mesmo se o e-mail já for de
            // outro Seller/SuperAdmin.
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('role', '!=', Role::Client->value)),
            ],
            // O documento é único por papel, não globalmente — quem já é Cliente
            // (convidado por um vendedor) pode se cadastrar aqui como Seller com o
            // mesmo CPF, contanto que use um e-mail diferente (esse continua único).
            'document' => ['required', 'string', 'size:11', Rule::unique('users', 'document')->where('role', Role::Seller->value)],
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
