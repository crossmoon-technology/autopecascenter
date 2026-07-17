<?php

namespace App\Http\Controllers\AuthController\Exceptions;

use Illuminate\Validation\ValidationException;

class InvalidCredentialsException extends ValidationException
{
    public static function make(): static
    {
        return static::withMessages([
            'email' => __('auth.failed'),
        ]);
    }
}
