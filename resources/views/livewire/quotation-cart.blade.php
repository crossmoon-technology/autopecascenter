@php
    $items = $quotation?->items ?? collect();
    $itemCount = $items->count();
@endphp

<div>
    <style>
        .qc-trigger {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: inherit;
        }
        .qc-trigger:hover {
            background: rgba(127, 127, 127, 0.1);
        }
        .qc-trigger svg {
            width: 1.375rem;
            height: 1.375rem;
        }
        .qc-badge {
            position: absolute;
            top: 0.0625rem;
            right: 0.0625rem;
            min-width: 1.125rem;
            height: 1.125rem;
            padding: 0 0.25rem;
            border-radius: 9999px;
            background: #F94603;
            color: #fff;
            font-size: 0.625rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .qc-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
        .qc-items {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .qc-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            padding-bottom: 0.625rem;
            border-bottom: 1px solid rgba(127, 127, 127, 0.15);
        }
        .qc-item-body {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .qc-item-codigo {
            font-size: 0.875rem;
            font-weight: 700;
        }
        .qc-item-meta {
            font-size: 0.75rem;
            opacity: 0.65;
        }
        .qc-item-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.375rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
            background: transparent;
            color: inherit;
            opacity: 0.65;
            cursor: pointer;
        }
        .qc-item-remove:hover {
            opacity: 1;
            border-color: rgb(220 38 38);
            color: rgb(220 38 38);
        }
        .qc-item-remove svg {
            width: 0.875rem;
            height: 0.875rem;
        }
        .qc-save-panel {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .qc-save-input {
            width: 100%;
            padding: 0.4375rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.3);
            background: transparent;
            color: inherit;
            font-size: 0.8125rem;
        }
        .qc-save-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background-color: #F94603;
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        .qc-save-btn:hover {
            opacity: 0.9;
        }
    </style>

    <x-filament::modal
        id="quotation-cart"
        slide-over
        teleport="body"
        width="sm"
        icon="heroicon-o-shopping-cart"
        :icon-color="$itemCount ? 'primary' : 'gray'"
    >
        <x-slot name="trigger">
            <button type="button" class="qc-trigger" title="Cotação em aberto">
                <x-filament::icon icon="heroicon-o-shopping-cart" />
                @if ($itemCount > 0)
                    <span class="qc-badge">{{ $itemCount }}</span>
                @endif
            </button>
        </x-slot>

        <x-slot name="heading">
            Cotação em aberto
        </x-slot>

        @if ($itemCount === 0)
            <p class="qc-empty">
                Sua cotação está vazia. Adicione peças pela Base de dados, API, Iframes ou Favoritas — elas aparecem
                aqui na hora.
            </p>
        @else
            <div class="qc-items">
                @foreach ($items as $item)
                    <div class="qc-item" wire:key="qc-item-{{ $item->id }}">
                        <div class="qc-item-body">
                            <span class="qc-item-codigo">{{ $item->quantity }}x {{ $item->codigo }}</span>
                            @if ($item->manufacturer)
                                <span class="qc-item-meta">{{ $item->manufacturer->name }}</span>
                            @endif
                        </div>

                        <button
                            type="button"
                            wire:click="removeItem({{ $item->id }})"
                            class="qc-item-remove"
                            title="Remover"
                        >
                            <x-filament::icon icon="heroicon-o-x-mark" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($itemCount > 0)
            <x-slot name="footer">
                <div class="qc-save-panel">
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="Nome da cotação (opcional)"
                        class="qc-save-input"
                    >
                    <button type="button" wire:click="save" class="qc-save-btn">
                        <x-filament::icon icon="heroicon-o-check-circle" />
                        Salvar cotação
                    </button>
                </div>
            </x-slot>
        @endif
    </x-filament::modal>
</div>
