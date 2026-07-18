<x-filament-panels::page>
    <style>
        /* Utilitários Tailwind arbitrários no blade da página nem sempre existem no CSS
           pré-compilado do Filament, então o grid aqui é feito com CSS puro pra garantir. */
        .pe-results-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .pe-search-form-wrap {
            margin-top: 1.5rem;
        }

        /* Chips de fabricante em vez de uma lista vertical de switches — com muitos fabricantes
           cadastrados, uma lista vertical ia exigir scroll e ocupar a tela toda; os chips quebram
           linha (flex-wrap) e cabem vários por linha. */
        .pe-manufacturers-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .pe-manufacturer-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3125rem 0.875rem 0.3125rem 0.3125rem;
            border-radius: 9999px;
            border: 1px solid rgba(127, 127, 127, 0.3);
            background: transparent;
            color: inherit;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            opacity: 0.55;
            transition: opacity 0.15s ease, border-color 0.15s ease, background-color 0.15s ease;
        }
        .pe-manufacturer-chip:hover {
            opacity: 0.8;
        }
        .pe-manufacturer-chip-active {
            opacity: 1;
            border-color: rgb(37 99 235);
            background: rgba(37, 99, 235, 0.1);
        }
        .pe-manufacturer-chip-active:hover {
            opacity: 1;
        }
        .pe-manufacturer-chip:focus-visible {
            outline: 2px solid rgb(37 99 235);
            outline-offset: 2px;
        }
        .pe-manufacturer-chip-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 1.5rem;
            height: 1.5rem;
            flex-shrink: 0;
            padding: 3px;
            border-radius: 9999px;
            background: #fff;
            overflow: hidden;
        }
        .pe-manufacturer-chip-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .pe-manufacturer-chip-icon-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }

        .pe-result-placeholder {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .pe-result-placeholder-text {
            font-size: 0.875rem;
            opacity: 0.65;
        }

        .pe-search-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background-color: rgb(37 99 235);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: background-color 0.15s ease, opacity 0.15s ease;
        }
        .pe-search-button:hover:not(:disabled) {
            background-color: rgb(29 78 216);
        }
        .pe-search-button:disabled {
            cursor: not-allowed;
        }
    </style>

    {{--
        manufacturers entangla data.manufacturers inteiro (não fabricante por fabricante) num único
        escopo Alpine compartilhado entre os chips e o botão, pra calcular "tem algum marcado?"
        reativamente no navegador, sem round-trip pro servidor a cada clique.
    --}}
    <div x-data="{ manufacturers: $wire.entangle('data.manufacturers') }">
        <x-filament::section>
            <x-slot name="heading">Fabricantes</x-slot>
            <x-slot name="description">Desmarque os que não quer incluir nesta busca.</x-slot>

            <p
                x-show="! Object.values(manufacturers).some(v => v)"
                x-cloak
                style="font-size: 0.875rem; color: rgb(220 38 38); margin-bottom: 0.75rem;"
            >
                Selecione ao menos um fabricante para buscar.
            </p>

            <div class="pe-manufacturers-wrap">
                @foreach ($this->activeManufacturers() as $manufacturer)
                    @php $manufacturerImage = $manufacturer->icon ?? $manufacturer->logo; @endphp

                    <button
                        type="button"
                        x-on:click="manufacturers['{{ $manufacturer->id }}'] = ! manufacturers['{{ $manufacturer->id }}']"
                        x-bind:aria-pressed="manufacturers['{{ $manufacturer->id }}']?.toString()"
                        x-bind:class="manufacturers['{{ $manufacturer->id }}'] ? 'pe-manufacturer-chip-active' : ''"
                        class="pe-manufacturer-chip"
                    >
                        @if ($manufacturerImage)
                            <span class="pe-manufacturer-chip-icon">
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($manufacturerImage) }}"
                                    alt=""
                                >
                            </span>
                        @else
                            <span class="pe-manufacturer-chip-icon pe-manufacturer-chip-icon-placeholder"></span>
                        @endif
                        <span>{{ $manufacturer->name }}</span>
                    </button>
                @endforeach
            </div>
        </x-filament::section>

        <div class="pe-search-form-wrap">
            <form wire:submit="search">
                {{ $this->form }}

                <button
                    type="submit"
                    x-bind:disabled="! Object.values(manufacturers).some(v => v)"
                    x-bind:style="{ opacity: Object.values(manufacturers).some(v => v) ? 1 : 0.5 }"
                    class="pe-search-button"
                >
                    Buscar
                </button>
            </form>
        </div>
    </div>

    @if ($hasSearched)
        @php
            $selectedManufacturers = $this->activeManufacturers()
                ->whereIn('id', $this->selectedManufacturerIds());
        @endphp

        <div class="pe-results-grid">
            @foreach ($selectedManufacturers as $manufacturer)
                <x-filament::section wire:key="pe-result-{{ $manufacturer->id }}">
                    <x-slot name="heading">{{ $manufacturer->name }}</x-slot>

                    <div class="pe-result-placeholder">
                        <x-filament::loading-indicator style="width: 1.25rem; height: 1.25rem; color: rgb(37 99 235);" />
                        <p class="pe-result-placeholder-text">A busca por equivalência ainda não foi implementada.</p>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
