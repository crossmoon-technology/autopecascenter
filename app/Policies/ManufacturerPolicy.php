<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Cadastro de fabricantes é coisa de SuperAdmin — contas de vendedor (Role::Admin) não
 * têm acesso nenhum aqui (nem o menu aparece, ver HasAuthorization::canAccess() ==
 * canViewAny()). Não confundir com App\Filament\Pages\Configuracoes\ManufacturerPreferences,
 * que é a tela (separada) onde o vendedor escolhe QUAIS fabricantes já cadastrados
 * aparecem pros clientes dele — essa continua liberada.
 */
class ManufacturerPolicy
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
