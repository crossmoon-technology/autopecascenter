<?php

namespace App\Filament\Pages\Buscas;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ViewClient extends Page
{
    protected static ?string $slug = 'clientes/{client}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.buscas.view-client';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|UnitEnum|null $navigationGroup = 'Vendas';

    public User $currentClient;

    /**
     * findOrFail() aqui vem de Auth::user()->clients() (não User::query()) de propósito
     * — um vendedor só consegue ver clientes vinculados a ele mesmo, mesmo que o cliente
     * tenha outros vendedores também (ver User::clients()). Os pedidos carregados também
     * são filtrados pro mesmo vendedor, já que um cliente com múltiplos vendedores pode
     * ter pedidos que não são desse vendedor específico.
     */
    public function mount(int|string $client): void
    {
        $this->currentClient = Auth::user()->clients()
            ->with(['orders' => fn ($query) => $query->forSeller(Auth::user())->with(['items.preferredManufacturers', 'quotation'])])
            ->findOrFail($client);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->currentClient->name;
    }
}
