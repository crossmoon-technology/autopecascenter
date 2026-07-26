<?php

namespace App\Models\User\Enums;

enum Plan: int
{
    case Basico = 1;
    case Profissional = 2;

    public function label(): string
    {
        return match ($this) {
            self::Basico => 'Básico',
            self::Profissional => 'Profissional',
        };
    }
}
