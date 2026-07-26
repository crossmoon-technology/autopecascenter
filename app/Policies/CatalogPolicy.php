<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Cadastro de catálogos é coisa de SuperAdmin — contas de vendedor (Role::Seller) não têm
 * acesso nenhum aqui (nem o menu aparece, ver HasAuthorization::canAccess() ==
 * canViewAny()). Buscar peças nesses catálogos continua liberado pra todo mundo — isso é
 * a Base de dados (App\Filament\Pages\Buscas\CatalogDatabaseSearch), não esse resource.
 */
class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function view(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function update(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function delete(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function deleteAny(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function restore(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function forceDelete(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }
}
