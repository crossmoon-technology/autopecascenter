<?php

namespace App\Enums;

enum Role: int
{
    case SuperAdmin = 1;
    case Admin      = 2;
    case Client     = 3;

    public function label(): string
    {
        return match($this) {
            Role::SuperAdmin => 'Super Administrador',
            Role::Admin      => 'Administrador',
            Role::Client     => 'Cliente',
        };
    }
}
