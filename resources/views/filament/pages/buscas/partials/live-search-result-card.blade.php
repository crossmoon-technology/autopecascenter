@php
    $liveKey = "{$manufacturer->id}|{$result->codigo}";
    $isQuoted = in_array($liveKey, $quotedLiveKeys, true);
@endphp

<div class="pe-part-card" wire:key="pe-live-{{ $manufacturer->id }}-{{ $result->codigo }}">
    @if ($result->product_url)
        <a href="{{ $result->product_url }}" target="_blank" rel="noopener" class="pe-part-codigo">{!! $this->highlight($result->codigo) !!}</a>
    @else
        <span class="pe-part-codigo">{!! $this->highlight($result->codigo) !!}</span>
    @endif

    @if ($result->descricao !== '')
        <div class="pe-part-attributes">
            <span class="pe-part-attribute">{!! $this->highlight($result->descricao) !!}</span>
        </div>
    @endif

    @if ($result->aplicacao)
        <div class="pe-part-attributes">
            <span class="pe-part-attribute"><strong>Aplicação:</strong> {!! $this->highlight($result->aplicacao) !!}</span>
        </div>
    @endif

    @if (! empty($result->conversoes))
        <div class="pe-part-equivalences">
            <span class="pe-part-equivalences-label">Equivalências (clique para buscar):</span>
            <div class="pe-part-equivalence-group">
                @foreach ($result->conversoes as $equivalenceCode)
                    <button
                        type="button"
                        x-on:click="$wire.searchFor('{{ $equivalenceCode }}').then(ids => ids.forEach(id => $wire.fetchLiveResultFor(id)))"
                        wire:key="pe-live-equivalence-{{ $manufacturer->id }}-{{ $result->codigo }}-{{ $equivalenceCode }}"
                        class="pe-equivalence-chip"
                    >
                        {!! $this->highlight($equivalenceCode) !!}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="pe-part-actions">
        <button
            type="button"
            wire:click="addLiveResultToQuotation({{ $manufacturer->id }}, '{{ $result->codigo }}', @js($result->descricao))"
            class="pe-part-action-btn {{ $isQuoted ? 'pe-part-action-btn-quoted' : '' }}"
            title="{{ $isQuoted ? 'Remover da cotação' : 'Adicionar à cotação' }}"
        >
            <x-filament::icon icon="heroicon-o-shopping-cart" />
        </button>

        @if ($result->product_url)
            <a
                href="{{ $result->product_url }}"
                target="_blank"
                rel="noopener"
                class="pe-part-action-btn"
                title="Ver no site do fabricante"
            >
                <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" />
            </a>
        @endif
    </div>
</div>
