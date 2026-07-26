<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Filament\Http\Middleware\Authenticate;

class FilamentAuthenticate extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }

    public static function panelUrlForRole(Role $role): string
    {
        return match ($role) {
            Role::SuperAdmin => '/super-admin',
            Role::Admin => '/vendedor',
            Role::Client => '/cliente',
            Role::Seller => '/vendedor',
        };
    }
}
