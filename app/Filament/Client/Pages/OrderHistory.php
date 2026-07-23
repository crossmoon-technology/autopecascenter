<?php

namespace App\Filament\Client\Pages;

use App\Models\Order;
use App\Models\Order\Enums\Status;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class OrderHistory extends Page
{
    protected string $view = 'filament.client.pages.order-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'Histórico de pedidos';

    protected static ?string $title = 'Histórico de pedidos';

    /**
     * @return Collection<int, Order>
     */
    public function orders(): Collection
    {
        return Auth::user()->orders()->with('items.preferredManufacturers')->latest()->get();
    }

    /**
     * O cliente só pode cancelar um pedido enquanto o vendedor ainda não começou a
     * processá-lo de verdade — uma vez Finalizado ou já Cancelado, não faz mais sentido.
     */
    public function cancelOrder(int $order_id): void
    {
        $order = Auth::user()->orders()->whereKey($order_id)->first();

        if (! $order || ! in_array($order->status, [Status::Pending, Status::Processing], true)) {
            return;
        }

        $order->update(['status' => Status::Cancelled]);

        // Genérico de propósito, mesmo hook usado em App\Filament\Client\Pages\CreateOrder
        // — ver resources/js/onboarding-tour.js.
        $this->dispatch('order-cancelled', orderId: $order->id);

        Notification::make()
            ->title('Pedido cancelado.')
            ->success()
            ->send();
    }
}
