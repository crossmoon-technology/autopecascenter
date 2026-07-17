<?php

namespace App\Http\Controllers\AuthController\Exceptions;

use Illuminate\Validation\ValidationException;

class InvalidResetTokenException extends ValidationException
{
    public static function make(string $status): static
    {
        return static::withMessages([
            'email' => __($status),
        ]);
    }
}
