<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanChoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_choice' => [
                'required', 'string', 'in:trial,basico,profissional',
                // A avaliação gratuita é de uso único por conta — trial_ends_at só é
                // nulo antes da primeira escolha de plano (ver AuthService::choosePlan()),
                // então já ter um valor aqui significa que a conta já passou por um
                // período de avaliação antes, vencido ou não.
                Rule::prohibitedIf(fn (): bool => $this->input('plan_choice') === 'trial' && $this->user()?->trial_ends_at !== null),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_choice.prohibited' => 'Você já usou sua avaliação gratuita — escolha um plano pra continuar.',
        ];
    }
}
