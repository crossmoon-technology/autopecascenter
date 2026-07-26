<?php

namespace App\Enums;

enum Role: int
{
    case SuperAdmin = 1;

    /**
     * Reservado pra um futuro ator do sistema — não é mais o vendedor (ver Role::Seller),
     * e nenhuma conta deve ter esse valor hoje (contas antigas foram migradas, ver
     * a migration 2026_08_08_010000_reassign_legacy_admin_role_users_to_seller).
     */
    case Admin = 2;

    case Client = 3;
    case Seller = 4;

    public function label(): string
    {
        return match ($this) {
            Role::SuperAdmin => 'Super Administrador',
            Role::Admin => 'Administrador',
            Role::Client => 'Cliente',
            Role::Seller => 'Vendedor',
        };
    }

    public function panelId(): string
    {
        return match ($this) {
            Role::SuperAdmin => 'super-admin',
            Role::Admin => 'admin',
            Role::Client => 'client',
            Role::Seller => 'admin',
        };
    }
}
