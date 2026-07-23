<?php

namespace App\Filament\Pages\Buscas;

use App\Models\OrderLink;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ViewOrderLink extends Page
{
    protected static ?string $slug = 'links-pedido/{orderLink}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.buscas.view-order-link';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Vendas';

    public OrderLink $currentOrderLink;

    public function mount(int|string $orderLink): void
    {
        $this->currentOrderLink = auth()->user()->orderLinks()
            ->with('registeredUser.orders.items.preferredManufacturers', 'registeredUser.orders.quotation')
            ->findOrFail($orderLink);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->currentOrderLink->displayLabel();
    }

    /**
     * Só dispara quando o vendedor clica em "Gerar link" — não é gerado sozinho ao criar
     * o registro nem a cada carregamento dessa página (ver OrderLink::generateToken()).
     */
    public function generateLink(): void
    {
        $this->currentOrderLink->generateToken();
    }
}
