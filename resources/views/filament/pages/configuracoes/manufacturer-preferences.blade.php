<x-filament-panels::page>
    <style>
        /* Grade de cards quadrados — antes eram chips em pílula, pequenos demais pra ver de
           relance quais fabricantes estão habilitados; o card maior + rótulo de status deixa
           o estado de cada um óbvio sem precisar prestar atenção na cor da borda. */
        .mp-manufacturers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
            gap: 1rem;
        }
        .mp-manufacturer-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            aspect-ratio: 1 / 1;
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 2px solid rgba(127, 127, 127, 0.25);
            background: transparent;
            color: inherit;
            cursor: pointer;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }
        .mp-manufacturer-card:hover {
            border-color: rgba(37, 99, 235, 0.5);
        }
        .mp-manufacturer-card:focus-visible {
            outline: 2px solid rgb(37 99 235);
            outline-offset: 2px;
        }
        .mp-manufacturer-card-active {
            border-color: rgb(37 99 235);
            background: rgba(37, 99, 235, 0.08);
        }
        .mp-manufacturer-card-active:hover {
            border-color: rgb(37 99 235);
        }
        .mp-manufacturer-card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 4.5rem;
            height: 4.5rem;
            flex-shrink: 0;
            padding: 0.625rem;
            border-radius: 0.625rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
            background: #fff;
            overflow: hidden;
        }
        .mp-manufacturer-card-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .mp-manufacturer-card-icon-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }
        .mp-manufacturer-card-name {
            font-size: 0.9375rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .mp-manufacturer-card-status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
            background: rgba(127, 127, 127, 0.14);
            color: rgb(107, 114, 128);
        }
        .mp-manufacturer-card-status-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
            background: currentColor;
            flex-shrink: 0;
        }
        .mp-manufacturer-card-status-active {
            background: rgba(34, 197, 94, 0.15);
            color: rgb(21, 128, 61);
        }
        .mp-save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1.25rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background-color: rgb(37 99 235);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .mp-save-button:hover {
            background-color: rgb(29 78 216);
        }
        .mp-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
    </style>

    @php
        $manufacturers = $this->availableManufacturers();
    @endphp

    @if ($manufacturers->isEmpty())
        <p class="mp-empty">Nenhum fabricante ativo cadastrado ainda.</p>
    @else
        <div x-data="{ manufacturers: $wire.entangle('manufacturers') }">
            <x-filament::section>
                <x-slot name="heading">Fabricantes</x-slot>
                <x-slot name="description">
                    Escolha os fabricantes que você costuma usar. Eles virão pré-selecionados nas páginas de
                    Base de dados, Iframes e API — sem precisar marcar de novo toda vez.
                </x-slot>

                <div class="mp-manufacturers-grid">
                    @foreach ($manufacturers as $manufacturer)
                        @php $manufacturerImage = $manufacturer->icon ?? $manufacturer->logo; @endphp

                        <button
                            type="button"
                            x-on:click="manufacturers[{{ $manufacturer->id }}] = ! manufacturers[{{ $manufacturer->id }}]"
                            x-bind:aria-pressed="manufacturers[{{ $manufacturer->id }}]?.toString()"
                            x-bind:class="manufacturers[{{ $manufacturer->id }}] ? 'mp-manufacturer-card-active' : ''"
                            class="mp-manufacturer-card"
                        >
                            @if ($manufacturerImage)
                                <span class="mp-manufacturer-card-icon">
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($manufacturerImage) }}"
                                        alt=""
                                    >
                                </span>
                            @else
                                <span class="mp-manufacturer-card-icon mp-manufacturer-card-icon-placeholder"></span>
                            @endif

                            <span class="mp-manufacturer-card-name">{{ $manufacturer->name }}</span>

                            <span
                                x-bind:class="manufacturers[{{ $manufacturer->id }}] ? 'mp-manufacturer-card-status-active' : ''"
                                class="mp-manufacturer-card-status"
                            >
                                <span class="mp-manufacturer-card-status-dot"></span>
                                <span x-text="manufacturers[{{ $manufacturer->id }}] ? 'Habilitado' : 'Desabilitado'"></span>
                            </span>
                        </button>
                    @endforeach
                </div>

                <button type="button" wire:click="save" class="mp-save-button">
                    Salvar preferências
                </button>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
