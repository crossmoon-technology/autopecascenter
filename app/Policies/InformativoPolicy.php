<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Vendedor (Role::Seller) só pode ver os informativos — SuperAdmin é quem cadastra/edita/
 * apaga. O menu continua aparecendo pro vendedor (viewAny/view liberados), só as ações de
 * escrita ficam escondidas (EditAction/DeleteAction do Filament já respeitam isso sozinhas).
 */
class InformativoPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Seller, Role::SuperAdmin], strict: true);
    }

    public function view(User $user): bool
    {
        return in_array($user->role, [Role::Seller, Role::SuperAdmin], strict: true);
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
