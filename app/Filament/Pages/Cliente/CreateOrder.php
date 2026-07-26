<?php

namespace App\Filament\Pages\Cliente;

use App\Filament\Client\Pages\CreateOrder as ClientCreateOrder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Reaproveita inteiramente App\Filament\Client\Pages\CreateOrder (mesmo formulário,
 * mesma lógica de escolher vendedor e enviar peças, mesmo targetUser() padrão =
 * Auth::user()) só mudando onde aparece — quem já foi Role::Client e virou Role::Seller
 * (ver AuthService::registerSeller()/User::hasClientHistory()) continua podendo criar
 * pedidos pros vendedores que já tinha como cliente, só que não consegue mais acessar o
 * painel de cliente pra isso.
 */
class CreateOrder extends ClientCreateOrder
{
    protected static string|UnitEnum|null $navigationGroup = 'Cliente';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Criar pedido';

    protected static ?string $title = 'Criar pedido como cliente';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasClientHistory() === true;
    }
}
