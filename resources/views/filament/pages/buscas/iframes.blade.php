<x-filament-panels::page>
    <style>
        /* Chips pra escolher quais fabricantes aparecem nas abas abaixo — mesmo padrão da
           página Base de dados, adaptado com prefixo próprio pra essa página. */
        .if-manufacturers-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .if-manufacturer-chip {
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
        .if-manufacturer-chip:hover {
            opacity: 0.8;
        }
        .if-manufacturer-chip-active {
            opacity: 1;
            border-color: rgb(249 70 3);
            background: rgba(249, 70, 3, 0.1);
        }
        .if-manufacturer-chip-active:hover {
            opacity: 1;
        }
        .if-manufacturer-chip:focus-visible {
            outline: 2px solid rgb(249 70 3);
            outline-offset: 2px;
        }
        .if-manufacturer-chip-icon {
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
        .if-manufacturer-chip-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .if-manufacturer-chip-icon-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }

        /* Painel único com abas "como do navegador" — só uma fica visível por vez (x-show
           troca display, nunca remove do DOM, pra não recarregar o iframe ao trocar). */
        .if-tabs-wrap {
            margin-top: 1.5rem;
        }
        .if-tabs-bar {
            display: grid;
            grid-template-rows: repeat(2, auto);
            grid-auto-flow: column;
            grid-auto-columns: minmax(3.75rem, 1fr);
            gap: 0.25rem;
            border-bottom: 1px solid rgba(127, 127, 127, 0.3);
        }
        .if-tab {
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
        .if-tab-name {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .if-tab:hover {
            opacity: 0.85;
        }
        .if-tab-active {
            opacity: 1;
            font-weight: 600;
            background: rgba(249, 70, 3, 0.12);
            border-color: rgb(249 70 3);
            border-bottom: 1px solid transparent;
            color: rgb(249 70 3);
        }
        .if-tab-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
            padding: 2px;
            border-radius: 0.25rem;
            background: #fff;
            overflow: hidden;
        }
        .if-tab-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .if-tab-icon-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }
        .if-tab-panel {
            padding-top: 1rem;
        }
        .if-iframe {
            width: 100%;
            min-height: 640px;
            border: 1px solid rgba(127, 127, 127, 0.2);
            border-radius: 0.5rem;
        }
        .if-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }

        /* Como o conteúdo do iframe é opaco pra gente, não dá pra "capturar" um resultado
           — em vez disso, um input simples deixa o usuário digitar o código e adicioná-lo
           manualmente à cotação em aberto. */
        .if-add-quotation {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .if-add-quotation-input {
            flex: 1;
            max-width: 20rem;
            padding: 0.4375rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.3);
            background: transparent;
            color: inherit;
            font-size: 0.8125rem;
        }
        .if-add-quotation-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.4375rem 0.875rem;
            border-radius: 0.5rem;
            border: none;
            background-color: rgb(249 70 3);
            color: #fff;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
        }
        .if-add-quotation-btn:hover {
            background-color: rgb(199 56 2);
        }
        .if-add-quotation-btn svg {
            width: 0.875rem;
            height: 0.875rem;
        }
    </style>

    @php
        $manufacturers = $this->manufacturersWithIframe();
    @endphp

    @if ($manufacturers->isEmpty())
        <p class="if-empty">Nenhum fabricante com iframe cadastrado ainda. Cadastre a URL do iframe na edição do fabricante.</p>
    @else
        <div
            x-data="{
                manufacturers: $wire.entangle('manufacturers'),
                activeTab: {{ $manufacturers->first()->id }},
                toggle(id) {
                    this.manufacturers[id] = ! this.manufacturers[id];

                    if (! this.manufacturers[id] && this.activeTab === id) {
                        const next = Object.entries(this.manufacturers).find(([, checked]) => checked);
                        this.activeTab = next ? parseInt(next[0]) : null;
                    } else if (this.manufacturers[id] && this.activeTab === null) {
                        this.activeTab = id;
                    }
                },
                anySelected() {
                    return Object.values(this.manufacturers).some((v) => v);
                },
            }"
        >
            <x-filament::section>
                <x-slot name="heading">Fabricantes</x-slot>
                <x-slot name="description">Escolha quais iframes aparecem nas abas abaixo.</x-slot>

                <p
                    x-show="! anySelected()"
                    x-cloak
                    style="font-size: 0.875rem; color: rgb(220 38 38); margin-bottom: 0.75rem;"
                >
                    Selecione ao menos um fabricante para ver o iframe.
                </p>

                <div class="if-manufacturers-wrap">
                    @foreach ($manufacturers as $manufacturer)
                        @php $chipImage = $manufacturer->icon ?? $manufacturer->logo; @endphp

                        <button
                            type="button"
                            x-on:click="toggle({{ $manufacturer->id }})"
                            x-bind:aria-pressed="manufacturers[{{ $manufacturer->id }}]?.toString()"
                            x-bind:class="manufacturers[{{ $manufacturer->id }}] ? 'if-manufacturer-chip-active' : ''"
                            class="if-manufacturer-chip"
                        >
                            @if ($chipImage)
                                <span class="if-manufacturer-chip-icon">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($chipImage) }}" alt="">
                                </span>
                            @else
                                <span class="if-manufacturer-chip-icon if-manufacturer-chip-icon-placeholder"></span>
                            @endif
                            <span>{{ $manufacturer->name }}</span>
                        </button>
                    @endforeach
                </div>
            </x-filament::section>

            <div class="if-tabs-wrap" x-show="anySelected()" x-cloak>
                <div class="if-tabs-bar" role="tablist">
                    @foreach ($manufacturers as $manufacturer)
                        @php $tabImage = $manufacturer->icon ?? $manufacturer->logo; @endphp

                        <button
                            type="button"
                            role="tab"
                            x-show="manufacturers[{{ $manufacturer->id }}]"
                            x-on:click="activeTab = {{ $manufacturer->id }}"
                            x-bind:aria-selected="(activeTab === {{ $manufacturer->id }}).toString()"
                            x-bind:class="activeTab === {{ $manufacturer->id }} ? 'if-tab-active' : ''"
                            class="if-tab"
                        >
                            @if ($tabImage)
                                <span class="if-tab-icon">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($tabImage) }}" alt="">
                                </span>
                            @else
                                <span class="if-tab-icon if-tab-icon-placeholder"></span>
                            @endif
                            <span class="if-tab-name">{{ $manufacturer->name }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach ($manufacturers as $manufacturer)
                    <div
                        class="if-tab-panel"
                        x-show="activeTab === {{ $manufacturer->id }} && manufacturers[{{ $manufacturer->id }}]"
                        x-cloak
                    >
                        <div class="if-add-quotation" x-data="{ codigo: '' }">
                            <input
                                type="text"
                                x-model="codigo"
                                placeholder="Código da peça em {{ $manufacturer->name }}"
                                class="if-add-quotation-input"
                                x-on:keydown.enter="$wire.addToQuotation({{ $manufacturer->id }}, codigo); codigo = ''"
                            >
                            <button
                                type="button"
                                class="if-add-quotation-btn"
                                x-on:click="$wire.addToQuotation({{ $manufacturer->id }}, codigo); codigo = ''"
                            >
                                <x-filament::icon icon="heroicon-o-shopping-cart" />
                                Adicionar à cotação
                            </button>
                        </div>

                        <iframe
                            wire:ignore
                            src="{{ $manufacturer->iframe_url }}"
                            title="Busca — {{ $manufacturer->name }}"
                            class="if-iframe"
                            referrerpolicy="no-referrer"
                            sandbox="allow-same-origin allow-scripts allow-popups allow-forms"
                        ></iframe>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
