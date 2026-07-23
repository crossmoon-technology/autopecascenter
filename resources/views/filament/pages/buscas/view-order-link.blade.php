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
        .vol-status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .vol-status-open {
            background: rgba(234, 179, 8, 0.15);
            color: rgb(161 98 7);
        }
        .vol-status-used {
            background: rgba(34, 197, 94, 0.15);
            color: rgb(21 128 61);
        }
        .dark .vol-status-open {
            background: rgba(234, 179, 8, 0.2);
            color: rgb(250 204 21);
        }
        .dark .vol-status-used {
            background: rgba(34, 197, 94, 0.2);
            color: rgb(74 222 128);
        }
        .vol-client-name {
            font-weight: 600;
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
        .vol-share-panel {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .vol-share-url {
            flex: 1;
            min-width: 0;
            font-size: 0.8125rem;
            font-family: ui-monospace, monospace;
            word-break: break-all;
            color: rgb(249 70 3);
            text-decoration: none;
        }
        .vol-share-url:hover {
            text-decoration: underline;
        }
        .vol-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 2.25rem;
            height: 2.25rem;
            flex-shrink: 0;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
            background: transparent;
            color: inherit;
            opacity: 0.7;
            cursor: pointer;
            text-decoration: none;
        }
        .vol-action-btn:hover {
            opacity: 1;
            border-color: rgb(249 70 3);
        }
        .vol-action-btn svg {
            width: 1.125rem;
            height: 1.125rem;
        }
        .vol-copied {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgb(21 128 61);
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

    <a href="{{ \App\Filament\Pages\Buscas\OrderLinks::getUrl() }}" class="vol-back-link">
        &larr; Todos os clientes
    </a>

    @php
        $orders = $currentOrderLink->registeredUser?->orders ?? collect();
        $statusClass = match ($currentOrderLink->statusColor()) {
            'success' => 'vol-status-used',
            default => 'vol-status-open',
        };
    @endphp

    <div>
        <span class="vol-status {{ $statusClass }}">{{ $currentOrderLink->statusLabel() }}</span>
    </div>

    @if (! $currentOrderLink->isUsed() && ! $currentOrderLink->hasToken())
        <x-filament::section>
            <x-slot name="heading">Nenhum link gerado ainda</x-slot>
            <x-slot name="description">
                Esse cliente foi cadastrado sem um link de convite. Gere um quando quiser que ele mesmo envie a lista de peças.
            </x-slot>

            <x-filament::button wire:click="generateLink" icon="heroicon-o-link">
                Gerar link
            </x-filament::button>
        </x-filament::section>
    @elseif (! $currentOrderLink->isUsed())
        <x-filament::section>
            <x-slot name="heading">Link pra enviar ao cliente</x-slot>
            <x-slot name="description">
                Esse link só pode ser usado uma vez — assim que o cliente enviar a lista, ele deixa de funcionar.
            </x-slot>

            @php $shareUrl = $currentOrderLink->publicUrl(); @endphp

            <div class="vol-share-panel" x-data="{ copied: false }">
                <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="vol-share-url">{{ $shareUrl }}</a>

                <button
                    type="button"
                    class="vol-action-btn"
                    title="Copiar link"
                    x-on:click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 1500)"
                >
                    <x-filament::icon icon="heroicon-o-link" />
                </button>

                <a
                    href="https://wa.me/?text={{ urlencode('Me manda a lista de peças que você precisa: '.$shareUrl) }}"
                    target="_blank"
                    rel="noopener"
                    class="vol-action-btn"
                    title="Compartilhar no WhatsApp"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.288.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12.004 2c-5.514 0-9.99 4.476-9.99 9.99 0 1.76.464 3.484 1.346 5.001L2 22l5.135-1.342a9.96 9.96 0 0 0 4.869 1.242h.004c5.514 0 9.99-4.476 9.99-9.99 0-2.669-1.04-5.176-2.928-7.062A9.935 9.935 0 0 0 12.004 2zm0 18.156a8.15 8.15 0 0 1-4.157-1.137l-.298-.177-3.048.797.813-2.97-.194-.306a8.135 8.135 0 0 1-1.257-4.373c0-4.502 3.664-8.166 8.171-8.166a8.12 8.12 0 0 1 5.775 2.393 8.107 8.107 0 0 1 2.392 5.775c-.004 4.507-3.668 8.164-8.197 8.164z"/>
                    </svg>
                </a>

                <span x-show="copied" x-cloak class="vol-copied">Link copiado!</span>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Cliente cadastrado</x-slot>
            <x-slot name="description">Conta criada em {{ $currentOrderLink->used_at->format('d/m/Y H:i') }}.</x-slot>

            @if ($currentOrderLink->registeredUser)
                <div>
                    <div class="vol-client-name">{{ $currentOrderLink->registeredUser->name }}</div>
                    <div class="vol-client-email">{{ $currentOrderLink->registeredUser->email }}</div>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Histórico de pedidos</x-slot>
            <x-slot name="description">Listas enviadas pelo cliente, sem qualquer conversão ou consulta — confira e busque as peças correspondentes.</x-slot>

            @if ($orders->isEmpty())
                <p class="vol-empty">O cliente ainda não enviou nenhum pedido.</p>
            @else
                @foreach ($orders->sortByDesc('created_at') as $order)
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
    @endif
</x-filament-panels::page>
