<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Cadastro de peças é coisa de SuperAdmin — contas de vendedor (Role::Admin) não têm
 * acesso nenhum aqui (nem o menu aparece, ver HasAuthorization::canAccess() ==
 * canViewAny()). Buscar peças continua liberado pra todo mundo via Buscas — isso é
 * só o resource de cadastro/edição.
 */
class PartPolicy
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
