<?php

namespace App\Filament\Pages\Cliente;

use App\Filament\Client\Pages\OrderHistory as ClientOrderHistory;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Reaproveita inteiramente App\Filament\Client\Pages\OrderHistory (mesma view, mesma
 * lógica de listar/cancelar pedidos, mesmo targetUser() padrão = Auth::user()) só
 * mudando onde aparece — quem já foi Role::Client e virou Role::Seller (ver
 * AuthService::registerSeller()/User::hasClientHistory()) continua com os mesmos
 * pedidos de antes, só que não consegue mais acessar o painel de cliente pra vê-los.
 */
class OrderHistory extends ClientOrderHistory
{
    protected static string|UnitEnum|null $navigationGroup = 'Cliente';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Meus pedidos';

    protected static ?string $title = 'Meus pedidos como cliente';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasClientHistory() === true;
    }
}
