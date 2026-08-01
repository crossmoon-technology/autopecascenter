<x-filament-panels::page>
    <style>
        /* Utilitários Tailwind arbitrários no blade da página nem sempre existem no CSS
           pré-compilado do Filament, então o grid aqui é feito com CSS puro pra garantir. */
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
            border-color: rgb(249 70 3);
            background: rgba(249, 70, 3, 0.1);
        }
        .pe-manufacturer-chip-active:hover {
            opacity: 1;
        }
        .pe-manufacturer-chip:focus-visible {
            outline: 2px solid rgb(249 70 3);
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
        .pe-result-notice {
            font-size: 0.8125rem;
            opacity: 0.65;
        }
        .pe-result-heading {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }
        .pe-result-heading-icon {
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
        .pe-result-heading-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .pe-result-heading-icon-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }
        .pe-result-heading-count {
            opacity: 0.6;
            font-weight: 400;
        }
        .pe-part-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 0.75rem;
        }
        .pe-part-card {
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
        }
        .pe-part-codigo {
            font-size: 0.875rem;
            font-weight: 700;
            color: inherit;
            text-decoration: none;
        }
        .pe-part-codigo:hover {
            text-decoration: underline;
        }
        .pe-part-attributes {
            font-size: 0.75rem;
            opacity: 0.75;
            margin-top: 0.375rem;
        }
        .pe-part-attribute {
            display: block;
        }
        .pe-part-attribute strong {
            font-weight: 600;
            opacity: 0.85;
        }
        .pe-part-equivalences {
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(127, 127, 127, 0.15);
            font-size: 0.75rem;
        }
        .pe-part-equivalences-label {
            opacity: 0.6;
            display: block;
            margin-bottom: 0.25rem;
        }
        .pe-part-equivalence {
            display: block;
        }
        .pe-part-equivalence strong {
            font-weight: 600;
        }
        .pe-part-equivalence-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.25rem;
        }
        .pe-equivalence-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            border: 1px solid rgba(127, 127, 127, 0.3);
            background: transparent;
            color: inherit;
            font-size: 0.6875rem;
            font-weight: 600;
            cursor: pointer;
            transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .pe-equivalence-chip:hover {
            border-color: rgb(249 70 3);
            color: rgb(249 70 3);
            background: rgba(249, 70, 3, 0.08);
        }
        .pe-fallback-notice {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(234, 179, 8, 0.35);
            background: rgba(234, 179, 8, 0.1);
            color: rgb(146 64 14);
            font-size: 0.8125rem;
        }
        .pe-fallback-notice svg {
            width: 1.125rem;
            height: 1.125rem;
            flex-shrink: 0;
        }
        .pe-highlight {
            background: rgba(34, 197, 94, 0.55);
            color: inherit;
            padding: 0 0.125rem;
            border-radius: 0.1875rem;
            font-weight: 700;
        }

        .pe-part-actions {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            margin-top: 0.625rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(127, 127, 127, 0.15);
        }
        .pe-part-action-btn {
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
            transition: opacity 0.15s ease, border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .pe-part-action-btn:hover {
            opacity: 1;
            border-color: rgb(249 70 3);
        }
        .pe-part-action-btn svg {
            width: 0.875rem;
            height: 0.875rem;
        }
        .pe-part-action-btn-favorited {
            opacity: 1;
            border-color: rgb(234 179 8);
            background: rgba(234, 179, 8, 0.12);
            color: rgb(161 98 7);
        }
        .pe-part-action-btn-quoted {
            opacity: 1;
            border-color: rgb(34 197 94);
            background: rgba(34, 197, 94, 0.12);
            color: rgb(21 128 61);
        }
        .pe-part-action-copied {
            font-size: 0.6875rem;
            font-weight: 600;
            color: rgb(21 128 61);
        }

        .pe-tabs-wrap {
            margin-top: 1.5rem;
        }
        .pe-tabs-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            border-bottom: 1px solid rgba(127, 127, 127, 0.3);
        }
        .pe-tab {
            display: inline-flex;
            align-items: center;
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
        .pe-tab:hover {
            opacity: 0.85;
        }
        .pe-tab-has-results {
            opacity: 0.85;
            border-color: rgb(34 197 94);
            background: rgba(34, 197, 94, 0.12);
            color: rgb(21 128 61);
        }
        .pe-tab-active {
            opacity: 1;
            font-weight: 600;
            background: rgba(249, 70, 3, 0.12);
            border-color: rgb(249 70 3);
            border-bottom: 1px solid transparent;
            color: rgb(249 70 3);
        }
        .pe-tab-name {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .pe-tab-count {
            opacity: 0.7;
            font-weight: 400;
        }
        .pe-tab-panel {
            padding-top: 1rem;
        }
        .pe-tab-panel-scroll {
            max-height: 32rem;
            overflow-y: auto;
            padding-right: 0.25rem;
        }

        .pe-search-button {
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
        .pe-search-button:hover:not(:disabled) {
            background-color: rgb(199 56 2);
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
    {{--
        Atalho "/" foca o campo de busca de qualquer lugar da página (exceto quando já
        está digitando em outro campo), pra quem usa isso toda hora não precisar
        alcançar o mouse.
    --}}
    <div
        x-data="{ manufacturers: $wire.entangle('data.manufacturers') }"
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

        @if ($selectedManufacturers->isEmpty())
            <p class="pe-result-notice">Nenhum fabricante disponível para essa busca.</p>
        @else
            {{--
                Atalho "F" favorita a primeira peça da aba de fabricante ativa — evita ter
                que caçar o mouse até a estrela quando o resultado já é o esperado.
            --}}
            <div
                class="pe-tabs-wrap"
                x-data="{ activeTab: {{ $selectedManufacturers->first()->id }} }"
                x-on:keydown.window="
                    if ($event.key.toLowerCase() === 'f' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) {
                        $event.preventDefault();
                        document.querySelector('[data-manufacturer-panel=\'' + activeTab + '\'] .pe-part-action-btn')?.click();
                    }
                "
            >
                <div class="pe-tabs-bar" role="tablist">
                    @foreach ($selectedManufacturers as $manufacturer)
                        @php
                            $parts = $results[$manufacturer->id] ?? collect();
                            $manufacturerImage = $manufacturer->icon ?? $manufacturer->logo;
                        @endphp

                        <button
                            type="button"
                            role="tab"
                            x-on:click="activeTab = {{ $manufacturer->id }}"
                            x-bind:aria-selected="(activeTab === {{ $manufacturer->id }}).toString()"
                            x-bind:class="activeTab === {{ $manufacturer->id }} ? 'pe-tab-active' : ''"
                            class="pe-tab @if ($parts->isNotEmpty()) pe-tab-has-results @endif"
                        >
                            @if ($manufacturerImage)
                                <span class="pe-result-heading-icon">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($manufacturerImage) }}" alt="">
                                </span>
                            @else
                                <span class="pe-result-heading-icon pe-result-heading-icon-placeholder"></span>
                            @endif
                            <span class="pe-tab-name">{{ $manufacturer->name }} <span class="pe-tab-count">({{ $parts->count() }})</span></span>
                        </button>
                    @endforeach
                </div>

                @foreach ($selectedManufacturers as $manufacturer)
                    @php
                        $parts = $results[$manufacturer->id] ?? collect();
                    @endphp

                    <div
                        class="pe-tab-panel"
                        x-show="activeTab === {{ $manufacturer->id }}"
                        x-cloak
                        wire:key="pe-result-{{ $manufacturer->id }}"
                        data-manufacturer-panel="{{ $manufacturer->id }}"
                    >
                        @if ($parts->isEmpty())
                            <p class="pe-result-notice">Nenhum resultado encontrado em {{ $manufacturer->name }}.</p>
                        @else
                            <div class="pe-tab-panel-scroll">
                                <div class="pe-part-grid">
                                    @foreach ($parts as $part)
                                        @include('filament.pages.buscas.partials.catalog-search-part-card', ['part' => $part])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($usedFallbackScan)
            <div class="pe-fallback-notice">
                <x-filament::icon icon="heroicon-o-information-circle" />
                Alguma peça ainda não está no índice rápido de busca — usamos uma varredura mais lenta desta vez.
                Isso se resolve sozinho na próxima importação/atualização do catálogo dela.
            </div>
        @endif

        @php
            $exactManufacturers = $this->activeManufacturers()->filter(
                fn ($manufacturer) => ($exactResults[$manufacturer->id] ?? collect())->isNotEmpty()
            );
        @endphp

        <div class="pe-tabs-wrap">
            <x-filament::section>
                <x-slot name="heading">Peças exatas</x-slot>
                <x-slot name="description">
                    Busca o código próprio em TODOS os fabricantes ativos, ignorando os fabricantes marcados acima —
                    inclui também as peças de conversão de cada resultado encontrado.
                </x-slot>

                @if ($exactManufacturers->isEmpty())
                    <p class="pe-result-notice">Nenhuma peça exata (ou de conversão) encontrada para esse código.</p>
                @else
                    <div
                        x-data="{ activeExactTab: {{ $exactManufacturers->first()->id }} }"
                    >
                        <div class="pe-tabs-bar" role="tablist">
                            @foreach ($exactManufacturers as $manufacturer)
                                @php
                                    $exactParts = $exactResults[$manufacturer->id] ?? collect();
                                    $manufacturerImage = $manufacturer->icon ?? $manufacturer->logo;
                                @endphp

                                <button
                                    type="button"
                                    role="tab"
                                    x-on:click="activeExactTab = {{ $manufacturer->id }}"
                                    x-bind:aria-selected="(activeExactTab === {{ $manufacturer->id }}).toString()"
                                    x-bind:class="activeExactTab === {{ $manufacturer->id }} ? 'pe-tab-active' : ''"
                                    class="pe-tab"
                                >
                                    @if ($manufacturerImage)
                                        <span class="pe-result-heading-icon">
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($manufacturerImage) }}" alt="">
                                        </span>
                                    @else
                                        <span class="pe-result-heading-icon pe-result-heading-icon-placeholder"></span>
                                    @endif
                                    <span class="pe-tab-name">{{ $manufacturer->name }} <span class="pe-tab-count">({{ $exactParts->count() }})</span></span>
                                </button>
                            @endforeach
                        </div>

                        @foreach ($exactManufacturers as $manufacturer)
                            @php $exactParts = $exactResults[$manufacturer->id] ?? collect(); @endphp

                            <div
                                class="pe-tab-panel"
                                x-show="activeExactTab === {{ $manufacturer->id }}"
                                x-cloak
                                wire:key="pe-exact-result-{{ $manufacturer->id }}"
                            >
                                <div class="pe-tab-panel-scroll">
                                    <div class="pe-part-grid">
                                        @foreach ($exactParts as $part)
                                            @include('filament.pages.buscas.partials.catalog-search-part-card', ['part' => $part, 'clickableEquivalences' => true])
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
