<x-filament-panels::page>
    <style>
        .vol-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            opacity: 0.65;
            text-decoration: none;
            color: inherit;
            margin-bottom: 1rem;
        }
        .vol-back-link:hover {
            opacity: 1;
        }
        .vol-client-email {
            font-size: 0.8125rem;
            opacity: 0.7;
        }
        .vol-item-description {
            font-size: 0.9375rem;
        }
        .vol-item-quantity {
            font-weight: 700;
            opacity: 0.7;
            margin-right: 0.375rem;
        }
        .vol-item-manufacturers {
            margin-top: 0.25rem;
            font-size: 0.8125rem;
            opacity: 0.7;
        }
        .vol-order {
            margin-bottom: 1.25rem;
        }
        .vol-order:last-child {
            margin-bottom: 0;
        }
        .vol-order-date {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            opacity: 0.7;
            margin-bottom: 0.5rem;
        }
        .vol-order-status {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
        }
        .vol-order-status-pending {
            background: rgba(234, 179, 8, 0.15);
            color: rgb(161 98 7);
        }
        .vol-order-status-processing {
            background: rgba(59, 130, 246, 0.15);
            color: rgb(29 78 216);
        }
        .vol-order-status-finished {
            background: rgba(34, 197, 94, 0.15);
            color: rgb(21 128 61);
        }
        .vol-order-status-cancelled {
            background: rgba(220, 38, 38, 0.15);
            color: rgb(185 28 28);
        }
        .dark .vol-order-status-pending {
            background: rgba(234, 179, 8, 0.2);
            color: rgb(250 204 21);
        }
        .dark .vol-order-status-processing {
            background: rgba(59, 130, 246, 0.2);
            color: rgb(96 165 250);
        }
        .dark .vol-order-status-finished {
            background: rgba(34, 197, 94, 0.2);
            color: rgb(74 222 128);
        }
        .dark .vol-order-status-cancelled {
            background: rgba(220, 38, 38, 0.2);
            color: rgb(248 113 113);
        }
        .vol-order-quotation {
            margin-top: 0.375rem;
            font-size: 0.8125rem;
            opacity: 0.75;
        }
        .vol-order-notes {
            margin-top: 0.625rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            background: rgba(127, 127, 127, 0.1);
            font-size: 0.875rem;
            white-space: pre-line;
        }
        .vol-items {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .vol-item {
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
            font-size: 0.9375rem;
        }
        .vol-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
    </style>

    <a href="{{ \App\Filament\Pages\Buscas\Clients::getUrl() }}" class="vol-back-link">
        &larr; Todos os clientes
    </a>

    <x-filament::section>
        <x-slot name="heading">{{ $currentClient->name }}</x-slot>

        <div class="vol-client-email">{{ $currentClient->email }}</div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Histórico de pedidos</x-slot>
        <x-slot name="description">Listas enviadas pelo cliente, sem qualquer conversão ou consulta — confira e busque as peças correspondentes.</x-slot>

        @if ($currentClient->orders->isEmpty())
            <p class="vol-empty">O cliente ainda não enviou nenhum pedido.</p>
        @else
            @foreach ($currentClient->orders->sortByDesc('created_at') as $order)
                <div class="vol-order">
                    <div class="vol-order-date">
                        {{ $order->created_at->format('d/m/Y H:i') }}
                        <span class="vol-order-status vol-order-status-{{ $order->status->value }}">{{ $order->status->getLabel() }}</span>
                    </div>

                    <div class="vol-items">
                        @foreach ($order->items as $item)
                            <div class="vol-item">
                                <div class="vol-item-description"><span class="vol-item-quantity">{{ $item->quantity }}x</span>{{ $item->description }}</div>
                                @if ($item->preferredManufacturers->isNotEmpty())
                                    <div class="vol-item-manufacturers">Preferência: {{ $item->preferredManufacturers->pluck('name')->join(', ') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($order->notes)
                        <div class="vol-order-notes">{{ $order->notes }}</div>
                    @endif

                    @if ($order->quotation)
                        <div class="vol-order-quotation">Cotação: {{ $order->quotation->displayName() }}</div>
                    @endif
                </div>
            @endforeach
        @endif
    </x-filament::section>
</x-filament-panels::page>
