<?php

namespace App\Http\Controllers\AuthController\Exceptions;

use Illuminate\Validation\ValidationException;

class EmailNotVerifiedException extends ValidationException
{
    public const MESSAGE = 'Confirme seu e-mail antes de entrar. Enviamos um link de confirmação para o endereço cadastrado.';

    /**
     * O erro também vai pra uma chave própria (`unverified`), além de `email` —
     * é o que a view do login usa pra só mostrar o link "Reenviar e-mail de
     * confirmação" nesse caso específico, e não em qualquer erro de login
     * (ex: InvalidCredentialsException também usa a chave `email`).
     */
    public static function make(): static
    {
        return static::withMessages([
            'email' => self::MESSAGE,
            'unverified' => self::MESSAGE,
        ]);
    }
}
