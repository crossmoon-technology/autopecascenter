<?php

namespace App\Livewire;

use App\Filament\Pages\Buscas\Orders;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ícone no topbar, ao lado do carrinho de cotação — mostra quantos pedidos dos clientes
 * convidados por este vendedor ainda não foram finalizados (ver App\Models\Order\Enums\Status
 * e App\Filament\Pages\Buscas\Orders), com um link direto pra lá.
 */
class PendingOrdersIndicator extends Component
{
    #[On('order-status-updated')]
    public function refresh(): void {}

    public function pendingOrdersCount(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return Order::query()
            ->forSeller(Auth::user())
            ->whereNotIn('status', [Status::Finished, Status::Cancelled])
            ->count();
    }

    public function render(): View
    {
        return view('livewire.pending-orders-indicator', [
            'pendingOrdersCount' => $this->pendingOrdersCount(),
            'ordersUrl' => Orders::getUrl(),
        ]);
    }
}
