<x-filament-panels::page>
    <style>
        .mo-items {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .mo-item {
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
        }
        .mo-item-description {
            font-size: 0.9375rem;
        }
        .mo-item-quantity {
            font-weight: 700;
            opacity: 0.7;
            margin-right: 0.375rem;
        }
        .mo-item-manufacturers {
            margin-top: 0.25rem;
            font-size: 0.8125rem;
            opacity: 0.7;
        }
        .mo-order {
            margin-bottom: 1.25rem;
        }
        .mo-order:last-child {
            margin-bottom: 0;
        }
        .mo-order-date {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            opacity: 0.7;
            margin-bottom: 0.5rem;
        }
        .mo-order-status {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
        }
        .mo-order-status-pending {
            background: rgba(234, 179, 8, 0.15);
            color: rgb(161 98 7);
        }
        .mo-order-status-processing {
            background: rgba(59, 130, 246, 0.15);
            color: rgb(29 78 216);
        }
        .mo-order-status-finished {
            background: rgba(34, 197, 94, 0.15);
            color: rgb(21 128 61);
        }
        .mo-order-status-cancelled {
            background: rgba(220, 38, 38, 0.15);
            color: rgb(185 28 28);
        }
        .dark .mo-order-status-pending {
            background: rgba(234, 179, 8, 0.2);
            color: rgb(250 204 21);
        }
        .dark .mo-order-status-processing {
            background: rgba(59, 130, 246, 0.2);
            color: rgb(96 165 250);
        }
        .dark .mo-order-status-finished {
            background: rgba(34, 197, 94, 0.2);
            color: rgb(74 222 128);
        }
        .dark .mo-order-status-cancelled {
            background: rgba(220, 38, 38, 0.2);
            color: rgb(248 113 113);
        }
        .mo-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
        .mo-order-notes {
            margin-top: 0.625rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            background: rgba(127, 127, 127, 0.1);
            font-size: 0.875rem;
            white-space: pre-line;
        }
        .mo-cancel-btn {
            display: inline-flex;
            align-items: center;
            margin-top: 0.625rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(220, 38, 38, 0.4);
            background: transparent;
            color: rgb(185 28 28);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
        }
        .mo-cancel-btn:hover {
            background: rgba(220, 38, 38, 0.1);
        }
        .dark .mo-cancel-btn {
            color: rgb(248 113 113);
        }
    </style>

    @php
        $orders = $this->orders();
        $cancellableStatuses = [\App\Models\Order\Enums\Status::Pending, \App\Models\Order\Enums\Status::Processing];
    @endphp

    <x-filament::section>
        @if ($orders->isEmpty())
            <p class="mo-empty">Nenhum pedido enviado ainda.</p>
        @else
            @foreach ($orders as $order)
                {{-- data-order-row é o alvo do App\Livewire\OnboardingTutorial (passos de
                     verificar status/cancelar), montado dinamicamente com o id do pedido
                     de teste criado durante o tour — ver resources/js/onboarding-tour.js. --}}
                <div class="mo-order" data-order-row="{{ $order->id }}">
                    <div class="mo-order-date">
                        {{ $order->created_at->format('d/m/Y H:i') }}
                        <span class="mo-order-status mo-order-status-{{ $order->status->value }}">{{ $order->status->getLabel() }}</span>
                    </div>

                    <div class="mo-items">
                        @foreach ($order->items as $item)
                            <div class="mo-item">
                                <div class="mo-item-description"><span class="mo-item-quantity">{{ $item->quantity }}x</span>{{ $item->description }}</div>
                                @if ($item->preferredManufacturers->isNotEmpty())
                                    <div class="mo-item-manufacturers">Preferência: {{ $item->preferredManufacturers->pluck('name')->join(', ') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($order->notes)
                        <div class="mo-order-notes">{{ $order->notes }}</div>
                    @endif

                    @if (in_array($order->status, $cancellableStatuses, true))
                        <button
                            type="button"
                            wire:click="cancelOrder({{ $order->id }})"
                            wire:confirm="Tem certeza que deseja cancelar esse pedido?"
                            class="mo-cancel-btn"
                        >
                            Cancelar pedido
                        </button>
                    @endif
                </div>
            @endforeach
        @endif
    </x-filament::section>
</x-filament-panels::page>
