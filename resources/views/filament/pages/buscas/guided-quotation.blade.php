<x-filament-panels::page>
    <style>
        .gq-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            opacity: 0.65;
            text-decoration: none;
            color: inherit;
            margin-bottom: 1rem;
        }
        .gq-back-link:hover {
            opacity: 1;
        }
        .gq-progress {
            font-size: 0.8125rem;
            font-weight: 600;
            opacity: 0.65;
            margin-bottom: 0.5rem;
        }
        .gq-item-description {
            font-size: 1.125rem;
            font-weight: 700;
        }
        .gq-item-meta {
            margin-top: 0.25rem;
            font-size: 0.8125rem;
            opacity: 0.7;
        }
        .gq-manufacturers-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .gq-manufacturer-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3125rem 0.875rem;
            border-radius: 9999px;
            border: 1px solid rgba(127, 127, 127, 0.3);
            background: transparent;
            color: inherit;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            opacity: 0.55;
        }
        .gq-manufacturer-chip:hover {
            opacity: 0.8;
        }
        .gq-manufacturer-chip-active {
            opacity: 1;
            border-color: rgb(249 70 3);
            background: rgba(249, 70, 3, 0.1);
        }
        .gq-search-form-wrap {
            margin-top: 1rem;
            display: flex;
            align-items: flex-end;
            gap: 0.75rem;
        }
        .gq-search-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background-color: rgb(249 70 3);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        .gq-skip-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.3);
            background: transparent;
            color: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        .gq-results {
            margin-top: 1.5rem;
        }
        .gq-manufacturer-heading {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 700;
            margin-bottom: 0.625rem;
        }
        .gq-manufacturer-count {
            opacity: 0.6;
            font-weight: 400;
        }
        .gq-empty-manufacturer {
            font-size: 0.8125rem;
            opacity: 0.6;
            margin-bottom: 1rem;
        }
        .gq-part-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .gq-part-card {
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
        }
        .gq-part-codigo {
            font-size: 0.875rem;
            font-weight: 700;
        }
        .gq-part-attributes {
            font-size: 0.75rem;
            opacity: 0.75;
            margin-top: 0.375rem;
        }
        .gq-part-attribute {
            display: block;
        }
        .gq-part-attribute strong {
            font-weight: 600;
            opacity: 0.85;
        }
        .gq-part-add-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            width: 100%;
            margin-top: 0.625rem;
            padding-top: 0.5rem;
            border: none;
            border-top: 1px solid rgba(127, 127, 127, 0.15);
            background: transparent;
            color: rgb(249 70 3);
            font-size: 0.8125rem;
            font-weight: 700;
            cursor: pointer;
        }
        .gq-part-add-btn:hover {
            color: rgb(199 56 2);
        }
        .gq-complete-intro {
            font-size: 0.875rem;
            opacity: 0.75;
            margin-bottom: 1rem;
        }
        .gq-review-items {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
            margin-bottom: 1.25rem;
        }
        .gq-review-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            padding-bottom: 0.625rem;
            border-bottom: 1px solid rgba(127, 127, 127, 0.15);
        }
        .gq-review-item-body {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .gq-review-item-codigo {
            font-size: 0.875rem;
            font-weight: 700;
        }
        .gq-review-item-meta {
            font-size: 0.75rem;
            opacity: 0.65;
        }
        .gq-review-item-remove {
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
        .gq-review-item-remove:hover {
            opacity: 1;
            border-color: rgb(220 38 38);
            color: rgb(220 38 38);
        }
        .gq-review-item-remove svg {
            width: 0.875rem;
            height: 0.875rem;
        }
        .gq-save-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .gq-save-input {
            flex: 1;
            min-width: 12rem;
            padding: 0.4375rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.3);
            background: transparent;
            color: inherit;
            font-size: 0.8125rem;
        }
        .gq-save-btn {
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
        .gq-save-btn:hover:not(:disabled) {
            opacity: 0.9;
        }
        .gq-save-btn:disabled {
            cursor: not-allowed;
        }
        .gq-confirm-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-size: 0.8125rem;
            cursor: pointer;
        }
        .gq-confirm-label input {
            cursor: pointer;
        }
    </style>

    <a href="{{ \App\Filament\Pages\Buscas\Orders::getUrl() }}" class="gq-back-link">
        &larr; Voltar aos pedidos
    </a>

    @if ($this->isComplete())
        <x-filament::section>
            <x-slot name="heading">Revisão da cotação</x-slot>
            <x-slot name="description">Todos os itens desse pedido já foram revisados. Confira antes de salvar — dá pra remover algum item se precisar.</x-slot>

            @php $reviewItems = $this->reviewItems(); @endphp

            @if ($reviewItems->isEmpty())
                <p class="gq-complete-intro">Nenhuma peça foi adicionada durante essa cotação guiada.</p>
            @else
                <div class="gq-review-items">
                    @foreach ($reviewItems as $reviewItem)
                        <div class="gq-review-item" wire:key="gq-review-{{ $reviewItem->id }}">
                            <div class="gq-review-item-body">
                                <span class="gq-review-item-codigo">{{ $reviewItem->quantity }}x {{ $reviewItem->codigo }}</span>
                                @if ($reviewItem->manufacturer)
                                    <span class="gq-review-item-meta">{{ $reviewItem->manufacturer->name }}</span>
                                @endif
                            </div>

                            <button
                                type="button"
                                wire:click="removeReviewItem({{ $reviewItem->id }})"
                                class="gq-review-item-remove"
                                title="Remover"
                            >
                                <x-filament::icon icon="heroicon-o-x-mark" />
                            </button>
                        </div>
                    @endforeach
                </div>

                <label class="gq-confirm-label">
                    <input type="checkbox" wire:model="reviewConfirmed">
                    Revisei os dados acima e confirmo que está tudo certo.
                </label>

                <form
                    wire:submit="saveQuotation"
                    class="gq-save-form"
                    x-data="{ confirmed: $wire.entangle('reviewConfirmed') }"
                >
                    <input
                        type="text"
                        wire:model="quotationName"
                        placeholder="Nome da cotação (opcional)"
                        class="gq-save-input"
                    >
                    <button
                        type="submit"
                        x-bind:disabled="! confirmed"
                        x-bind:style="{ opacity: confirmed ? 1 : 0.5 }"
                        class="gq-save-btn"
                    >
                        <x-filament::icon icon="heroicon-o-check-circle" />
                        Salvar cotação
                    </button>
                </form>
            @endif
        </x-filament::section>
    @else
        @php $item = $this->currentItem(); @endphp

        <x-filament::section>
            <div class="gq-progress">Item {{ $this->currentItemIndex + 1 }} de {{ $this->totalItems() }}</div>
            <div class="gq-item-description">{{ $item->description }}</div>
            <div class="gq-item-meta">
                Quantidade: {{ $item->quantity }}
                @if ($item->preferredManufacturers->isNotEmpty())
                    &middot; Preferência do cliente: {{ $item->preferredManufacturers->pluck('name')->join(', ') }}
                @endif
            </div>

            <div x-data="{ manufacturers: $wire.entangle('data.manufacturers') }">
                <div class="gq-manufacturers-wrap">
                    @foreach ($this->eligibleManufacturers() as $manufacturer)
                        <button
                            type="button"
                            x-on:click="manufacturers['{{ $manufacturer->id }}'] = ! manufacturers['{{ $manufacturer->id }}']"
                            x-bind:aria-pressed="manufacturers['{{ $manufacturer->id }}']?.toString()"
                            x-bind:class="manufacturers['{{ $manufacturer->id }}'] ? 'gq-manufacturer-chip-active' : ''"
                            class="gq-manufacturer-chip"
                        >
                            {{ $manufacturer->name }}
                        </button>
                    @endforeach
                </div>

                <div class="gq-search-form-wrap">
                    <form wire:submit="search" style="flex: 1; display: flex; gap: 0.75rem; align-items: flex-end;">
                        {{ $this->form }}

                        <button type="submit" class="gq-search-button">Buscar</button>
                    </form>

                    <button type="button" wire:click="skipItem" class="gq-skip-button">Pular item</button>
                </div>
            </div>
        </x-filament::section>

        @if ($hasSearched)
            <div class="gq-results">
                @php $selectedManufacturers = $this->eligibleManufacturers()->whereIn('id', $this->selectedManufacturerIds()); @endphp

                @foreach ($selectedManufacturers as $manufacturer)
                    @php $parts = $results[$manufacturer->id] ?? collect(); @endphp

                    <div class="gq-manufacturer-heading">
                        {{ $manufacturer->name }} <span class="gq-manufacturer-count">({{ $parts->count() }})</span>
                    </div>

                    @if ($parts->isEmpty())
                        <p class="gq-empty-manufacturer">Nenhum resultado encontrado em {{ $manufacturer->name }}.</p>
                    @else
                        <div class="gq-part-grid">
                            @foreach ($parts as $part)
                                <div class="gq-part-card" wire:key="gq-part-{{ $part->id }}">
                                    <div class="gq-part-codigo">{{ $part->codigo }}</div>

                                    @if (! empty($part->atributos))
                                        <div class="gq-part-attributes">
                                            @foreach ($part->atributos as $chave => $valor)
                                                <span class="gq-part-attribute"><strong>{{ $chave }}:</strong> {{ $this->formatValue($valor) }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <button type="button" wire:click="addAndContinue({{ $part->id }})" class="gq-part-add-btn">
                                        <x-filament::icon icon="heroicon-o-check-circle" />
                                        Adicionar e avançar
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
