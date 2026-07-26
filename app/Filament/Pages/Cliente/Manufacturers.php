<?php

namespace App\Filament\Pages\Cliente;

use App\Filament\Client\Pages\Manufacturers as ClientManufacturers;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Reaproveita inteiramente App\Filament\Client\Pages\Manufacturers (mesma grade, mesmo
 * filtro por vendedor, mesmo targetUser() padrão = Auth::user()) só mudando onde aparece
 * — quem já foi Role::Client e virou Role::Seller (ver
 * AuthService::registerSeller()/User::hasClientHistory()) continua vendo os fabricantes
 * dos vendedores que já tinha como cliente, só que não consegue mais acessar o painel de
 * cliente pra isso.
 */
class Manufacturers extends ClientManufacturers
{
    // Sem isso, o slug padrão ("manufacturers") colide com a rota de índice do
    // ManufacturerResource nesse mesmo painel (admin/manufacturers).
    protected static ?string $slug = 'cliente-fabricantes';

    protected static string|UnitEnum|null $navigationGroup = 'Cliente';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Fabricantes';

    protected static ?string $title = 'Fabricantes como cliente';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasClientHistory() === true;
    }
}
