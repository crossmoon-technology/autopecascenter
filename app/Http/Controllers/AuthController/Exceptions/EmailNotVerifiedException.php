<?php

namespace App\Http\Controllers\AuthController\Exceptions;

use Illuminate\Validation\ValidationException;

class EmailNotVerifiedException extends ValidationException
{
    public const MESSAGE = 'Confirme seu e-mail antes de entrar. Enviamos um link de confirmação para o endereço cadastrado.';

    public static function make(): static
    {
        return static::withMessages([
            'email' => self::MESSAGE,
        ]);
    }
}
