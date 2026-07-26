<?php

namespace App\Filament\Pages\Cliente;

use App\Filament\Client\Pages\AddSeller as ClientAddSeller;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Reaproveita inteiramente App\Filament\Client\Pages\AddSeller (mesma lista, mesma
 * lógica de vincular/desanexar por código, mesmo targetUser() padrão = Auth::user()) só
 * mudando onde aparece — quem já foi Role::Client e virou Role::Seller (ver
 * AuthService::registerSeller()/User::hasClientHistory()) continua gerenciando os
 * vendedores que já tinha como cliente, só que não consegue mais acessar o painel de
 * cliente pra isso.
 */
class AddSeller extends ClientAddSeller
{
    protected static string|UnitEnum|null $navigationGroup = 'Cliente';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Meus vendedores';

    protected static ?string $title = 'Meus vendedores como cliente';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasClientHistory() === true;
    }
}
