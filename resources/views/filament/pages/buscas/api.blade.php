<x-filament-panels::page>
    <style>
        .ps-search-form-wrap {
            margin-top: 1.5rem;
        }
        .ps-manufacturers-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .ps-manufacturer-chip {
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
        .ps-manufacturer-chip:hover {
            opacity: 0.8;
        }
        .ps-manufacturer-chip-active {
            opacity: 1;
            border-color: rgb(249 70 3);
            background: rgba(249, 70, 3, 0.1);
        }
        .ps-manufacturer-chip-active:hover {
            opacity: 1;
        }
        .ps-manufacturer-chip:focus-visible {
            outline: 2px solid rgb(249 70 3);
            outline-offset: 2px;
        }
        .ps-manufacturer-chip-icon {
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
        .ps-manufacturer-chip-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .ps-manufacturer-chip-icon-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }
        .ps-search-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background-color: rgb(249 70 3);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: background-color 0.15s ease, opacity 0.15s ease;
        }
        .ps-search-button:hover:not(:disabled) {
            background-color: rgb(199 56 2);
        }
        .ps-search-button:disabled {
            cursor: not-allowed;
        }
        .ps-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
        .ps-results-wrap {
            margin-top: 1.5rem;
        }
        .ps-tabs-bar {
            display: grid;
            grid-template-rows: repeat(2, auto);
            grid-auto-flow: column;
            grid-auto-columns: minmax(9rem, 1fr);
            gap: 0.25rem;
            border-bottom: 1px solid rgba(127, 127, 127, 0.3);
        }
        .ps-tab {
            display: inline-flex;
            align-items: center;
            box-sizing: border-box;
            width: 100%;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: inherit;
            opacity: 0.6;
            background: rgba(127, 127, 127, 0.06);
            border: 1px solid rgba(127, 127, 127, 0.25);
            border-bottom: none;
            border-radius: 0.5rem 0.5rem 0 0;
            cursor: pointer;
            white-space: nowrap;
            position: relative;
            top: 1px;
            transition: opacity 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .ps-tab:hover {
            opacity: 0.85;
        }
        .ps-tab-active {
            opacity: 1;
            font-weight: 600;
            background: rgba(249, 70, 3, 0.12);
            border-color: rgb(249 70 3);
            border-bottom: 1px solid transparent;
            color: rgb(249 70 3);
        }
        .ps-tab-name {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .ps-section-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 1.75rem;
            height: 1.75rem;
            flex-shrink: 0;
            padding: 3px;
            border-radius: 9999px;
            background: #fff;
            overflow: hidden;
        }
        .ps-section-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .ps-section-logo-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }
        .ps-tab-count {
            opacity: 0.7;
            font-weight: 400;
        }
        .ps-tab-panel {
            padding-top: 1rem;
        }
        .ps-tab-panel-scroll {
            max-height: 32rem;
            overflow-y: auto;
            padding-right: 0.25rem;
        }
        .ps-notice {
            font-size: 0.8125rem;
            opacity: 0.65;
        }
        .ps-notice-error {
            color: rgb(220 38 38);
            opacity: 1;
        }
        .ps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.75rem;
        }
        .ps-card {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
        }
        .ps-card-image {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 3.5rem;
            height: 3.5rem;
            flex-shrink: 0;
            border-radius: 0.375rem;
            background: #fff;
            overflow: hidden;
        }
        .ps-card-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .ps-card-image-placeholder {
            background: rgba(127, 127, 127, 0.12);
        }
        .ps-card-body {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }
        .ps-card-codigo {
            font-size: 0.8125rem;
            font-weight: 700;
        }
        .ps-card-descricao {
            font-size: 0.8125rem;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .ps-card-meta {
            font-size: 0.75rem;
            opacity: 0.6;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ps-card-link {
            font-size: 0.75rem;
            color: rgb(249 70 3);
            text-decoration: none;
            margin-top: 0.25rem;
        }
        .ps-card-link:hover {
            text-decoration: underline;
        }

        .ps-card-actions {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            margin-top: 0.375rem;
        }
        .ps-card-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 1.75rem;
            height: 1.75rem;
            flex-shrink: 0;
            border-radius: 0.375rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
            background: transparent;
            color: inherit;
            opacity: 0.65;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.15s ease, border-color 0.15s ease;
        }
        .ps-card-action-btn:hover {
            opacity: 1;
            border-color: rgb(249 70 3);
        }
        .ps-card-action-btn svg {
            width: 0.875rem;
            height: 0.875rem;
        }
        .ps-card-action-copied {
            font-size: 0.6875rem;
            font-weight: 600;
            color: rgb(21 128 61);
        }
        .ps-card-action-btn-quoted {
            opacity: 1;
            border-color: rgb(34 197 94);
            background: rgba(34, 197, 94, 0.12);
            color: rgb(21 128 61);
        }
    </style>

    @php
        $scope = $this->searchableManufacturers();
    @endphp

    @if ($scope->isEmpty())
        <p class="ps-empty">Nenhum fabricante com busca via API disponível ainda.</p>
    @else
        <div
            x-data="{
                manufacturers: $wire.entangle('data.manufacturers'),
                searching: false,
                async runSearch() {
                    this.searching = true;

                    try {
                        const ids = await $wire.search();

                        if (Array.isArray(ids)) {
                            for (const id of ids) {
                                await $wire.searchManufacturer(id);
                            }
                        }
                    } finally {
                        this.searching = false;
                    }
                },
            }"
            x-on:keydown.window="
                if ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) {
                    $event.preventDefault();
                    document.getElementById('busca-termo-input')?.focus();
                }
            "
        >
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

                <div class="ps-manufacturers-wrap">
                    @foreach ($scope as $manufacturer)
                        @php $manufacturerImage = $manufacturer->icon ?? $manufacturer->logo; @endphp

                        <button
                            type="button"
                            x-on:click="manufacturers['{{ $manufacturer->id }}'] = ! manufacturers['{{ $manufacturer->id }}']"
                            x-bind:aria-pressed="manufacturers['{{ $manufacturer->id }}']?.toString()"
                            x-bind:class="manufacturers['{{ $manufacturer->id }}'] ? 'ps-manufacturer-chip-active' : ''"
                            class="ps-manufacturer-chip"
                        >
                            @if ($manufacturerImage)
                                <span class="ps-manufacturer-chip-icon">
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($manufacturerImage) }}"
                                        alt=""
                                    >
                                </span>
                            @else
                                <span class="ps-manufacturer-chip-icon ps-manufacturer-chip-icon-placeholder"></span>
                            @endif
                            <span>{{ $manufacturer->name }}</span>
                        </button>
                    @endforeach
                </div>
            </x-filament::section>

            <div class="ps-search-form-wrap">
                <form x-on:submit.prevent="runSearch()">
                    {{ $this->form }}

                    <button
                        type="submit"
                        x-bind:disabled="searching || ! Object.values(manufacturers).some(v => v)"
                        x-bind:style="{ opacity: (! searching && Object.values(manufacturers).some(v => v)) ? 1 : 0.5 }"
                        class="ps-search-button"
                    >
                        <span x-show="! searching">Buscar</span>
                        <span x-show="searching" x-cloak>Buscando…</span>
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($searched)
        @if (empty($results))
            <p class="ps-empty">Nenhum fabricante disponível para essa busca.</p>
        @else
            @php
                $firstEntry = array_values($results)[0];
            @endphp

            <div
                class="ps-results-wrap"
                x-data="{ activeTab: {{ $firstEntry['manufacturer']->id }} }"
            >
                <div class="ps-tabs-bar" role="tablist">
                    @foreach ($results as $entry)
                        @php
                            $manufacturer = $entry['manufacturer'];
                            $items = $entry['results'];
                            $status = $entry['status'];
                            $logo = $manufacturer->icon ?? $manufacturer->logo;
                        @endphp

                        <button
                            type="button"
                            role="tab"
                            x-on:click="activeTab = {{ $manufacturer->id }}"
                            x-bind:aria-selected="(activeTab === {{ $manufacturer->id }}).toString()"
                            x-bind:class="activeTab === {{ $manufacturer->id }} ? 'ps-tab-active' : ''"
                            class="ps-tab"
                            wire:key="ps-tab-{{ $manufacturer->id }}"
                        >
                            @if ($logo)
                                <span class="ps-section-logo">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="">
                                </span>
                            @else
                                <span class="ps-section-logo ps-section-logo-placeholder"></span>
                            @endif
                            <span class="ps-tab-name">
                                {{ $manufacturer->name }}
                                @if ($status === \App\Filament\Pages\Buscas\Api\Enums\SearchStatus::Success)
                                    <span class="ps-tab-count">({{ $items->count() }})</span>
                                @endif
                            </span>
                            @if ($status === \App\Filament\Pages\Buscas\Api\Enums\SearchStatus::Loading)
                                <x-filament::icon
                                    icon="heroicon-o-arrow-path"
                                    style="width: 1rem; height: 1rem; flex-shrink: 0; color: #F94603; animation: spin 2.5s linear infinite;"
                                />
                            @elseif ($status === \App\Filament\Pages\Buscas\Api\Enums\SearchStatus::Success)
                                <x-filament::icon
                                    icon="heroicon-o-check-circle"
                                    style="width: 1rem; height: 1rem; flex-shrink: 0; color: #22c55e;"
                                />
                            @else
                                <x-filament::icon
                                    icon="heroicon-o-x-circle"
                                    style="width: 1rem; height: 1rem; flex-shrink: 0; color: #ef4444;"
                                />
                            @endif
                        </button>
                    @endforeach
                </div>

                @foreach ($results as $entry)
                    @php
                        $manufacturer = $entry['manufacturer'];
                        $items = $entry['results'];
                        $status = $entry['status'];
                    @endphp

                    <div
                        class="ps-tab-panel"
                        x-show="activeTab === {{ $manufacturer->id }}"
                        x-cloak
                        wire:key="ps-result-{{ $manufacturer->id }}"
                    >
                        @if ($status === \App\Filament\Pages\Buscas\Api\Enums\SearchStatus::Loading)
                            <div style="display: flex; align-items: center; gap: 0.625rem;">
                                <x-filament::loading-indicator style="width: 1.25rem; height: 1.25rem; color: rgb(249 70 3);" />
                                <p class="ps-notice">Buscando em {{ $manufacturer->name }}…</p>
                            </div>
                        @elseif ($status === \App\Filament\Pages\Buscas\Api\Enums\SearchStatus::Failed)
                            <p class="ps-notice ps-notice-error">Não foi possível buscar em {{ $manufacturer->name }} agora. Tente novamente em instantes.</p>
                        @elseif ($items->isEmpty())
                            <p class="ps-notice">Nenhum resultado encontrado em {{ $manufacturer->name }}.</p>
                        @else
                            <div class="ps-tab-panel-scroll">
                                <div class="ps-grid">
                                    @foreach ($items as $item)
                                        @php $itemShareUrl = $this->itemShareUrl($item->codigo); @endphp

                                        @php $isQuoted = in_array("{$manufacturer->id}|{$item->codigo}", $quotedApiKeys, true); @endphp

                                        <div class="ps-card" wire:key="ps-item-{{ $manufacturer->id }}-{{ $loop->index }}">
                                            @if ($item->imagem_url)
                                                <span class="ps-card-image">
                                                    <img src="{{ $item->imagem_url }}" alt="" loading="lazy">
                                                </span>
                                            @else
                                                <span class="ps-card-image ps-card-image-placeholder"></span>
                                            @endif

                                            <div class="ps-card-body">
                                                <span class="ps-card-codigo">{{ $item->codigo }}</span>
                                                <span class="ps-card-descricao">{{ $item->descricao }}</span>
                                                @if ($item->montadora || $item->modelo)
                                                    <span class="ps-card-meta">{{ trim(($item->montadora ?? '').' '.($item->modelo ?? '')) }}</span>
                                                @endif
                                                @if ($item->product_url)
                                                    <a href="{{ $item->product_url }}" target="_blank" rel="noopener" class="ps-card-link">Ver no site &rarr;</a>
                                                @endif

                                                <div class="ps-card-actions" x-data="{ copied: false }">
                                                    <button
                                                        type="button"
                                                        wire:click="addToQuotation({{ $manufacturer->id }}, @js($item->codigo), @js($item->descricao))"
                                                        class="ps-card-action-btn {{ $isQuoted ? 'ps-card-action-btn-quoted' : '' }}"
                                                        title="{{ $isQuoted ? 'Remover da cotação' : 'Adicionar à cotação' }}"
                                                    >
                                                        <x-filament::icon icon="heroicon-o-shopping-cart" />
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="ps-card-action-btn"
                                                        title="Copiar link"
                                                        x-on:click="navigator.clipboard.writeText('{{ $itemShareUrl }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                                    >
                                                        <x-filament::icon icon="heroicon-o-link" />
                                                    </button>

                                                    <a
                                                        href="https://wa.me/?text={{ urlencode('Peça '.$item->codigo.': '.$itemShareUrl) }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="ps-card-action-btn"
                                                        title="Compartilhar no WhatsApp"
                                                    >
                                                        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.288.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                                            <path d="M12.004 2c-5.514 0-9.99 4.476-9.99 9.99 0 1.76.464 3.484 1.346 5.001L2 22l5.135-1.342a9.96 9.96 0 0 0 4.869 1.242h.004c5.514 0 9.99-4.476 9.99-9.99 0-2.669-1.04-5.176-2.928-7.062A9.935 9.935 0 0 0 12.004 2zm0 18.156a8.15 8.15 0 0 1-4.157-1.137l-.298-.177-3.048.797.813-2.97-.194-.306a8.135 8.135 0 0 1-1.257-4.373c0-4.502 3.664-8.166 8.171-8.166a8.12 8.12 0 0 1 5.775 2.393 8.107 8.107 0 0 1 2.392 5.775c-.004 4.507-3.668 8.164-8.197 8.164z"/>
                                                        </svg>
                                                    </a>

                                                    <span x-show="copied" x-cloak class="ps-card-action-copied">Copiado!</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
