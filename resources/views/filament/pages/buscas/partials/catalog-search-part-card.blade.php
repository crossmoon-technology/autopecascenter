@php
    $partViewUrl = $this->partViewUrl($part);
    $partPublicShareUrl = $part->publicShareUrl();
    $isFavorited = in_array($part->id, $favoritedPartIds, true);
    $isQuoted = in_array($part->id, $quotedPartIds, true);
    $clickableEquivalences = $clickableEquivalences ?? false;
@endphp

<div class="pe-part-card" wire:key="pe-part-{{ $part->id }}">
    <a href="{{ $partViewUrl }}" class="pe-part-codigo">{!! $this->highlight($part->codigo) !!}</a>
    @if (! empty($part->atributos))
        <div class="pe-part-attributes">
            @foreach ($part->atributos as $chave => $valor)
                <span class="pe-part-attribute"><strong>{{ $chave }}:</strong> {!! $this->highlight($this->formatValue($valor)) !!}</span>
            @endforeach
        </div>
    @endif
    @if (! empty($part->conversoes))
        <div class="pe-part-equivalences">
            <span class="pe-part-equivalences-label">Equivalências{{ $clickableEquivalences ? ' (clique para buscar)' : '' }}:</span>
            @if ($clickableEquivalences)
                @foreach ($part->conversoes as $marca => $codigos)
                    <div class="pe-part-equivalence-group">
                        <strong>{{ $marca }}:</strong>
                        @foreach ($this->equivalenceCodes($codigos) as $equivalenceCode)
                            <button
                                type="button"
                                wire:click="searchFor('{{ $equivalenceCode }}')"
                                wire:key="pe-equivalence-{{ $part->id }}-{{ $marca }}-{{ $equivalenceCode }}"
                                class="pe-equivalence-chip"
                            >
                                {!! $this->highlight($equivalenceCode) !!}
                            </button>
                        @endforeach
                    </div>
                @endforeach
            @else
                @foreach ($part->conversoes as $marca => $codigos)
                    <span class="pe-part-equivalence"><strong>{{ $marca }}:</strong> {!! $this->highlight($this->formatValue($codigos)) !!}</span>
                @endforeach
            @endif
        </div>
    @endif

    <div class="pe-part-actions" x-data="{ copied: false }">
        <button
            type="button"
            wire:click="toggleFavorite({{ $part->id }})"
            class="pe-part-action-btn {{ $isFavorited ? 'pe-part-action-btn-favorited' : '' }}"
            title="{{ $isFavorited ? 'Remover dos favoritos' : 'Favoritar' }}"
        >
            <x-filament::icon :icon="$isFavorited ? 'heroicon-s-star' : 'heroicon-o-star'" />
        </button>

        <button
            type="button"
            wire:click="mountAction('pickFavoriteList', { part_id: {{ $part->id }} })"
            class="pe-part-action-btn"
            title="Adicionar a uma lista"
        >
            <x-filament::icon icon="heroicon-o-folder-plus" />
        </button>

        <button
            type="button"
            wire:click="addToQuotation({{ $part->id }})"
            class="pe-part-action-btn {{ $isQuoted ? 'pe-part-action-btn-quoted' : '' }}"
            title="{{ $isQuoted ? 'Remover da cotação' : 'Adicionar à cotação' }}"
        >
            <x-filament::icon icon="heroicon-o-shopping-cart" />
        </button>

        <button
            type="button"
            class="pe-part-action-btn"
            title="Copiar link"
            x-on:click="navigator.clipboard.writeText('{{ $partPublicShareUrl }}'); copied = true; setTimeout(() => copied = false, 1500)"
        >
            <x-filament::icon icon="heroicon-o-link" />
        </button>

        <a
            href="https://wa.me/?text={{ urlencode('Peça '.$part->codigo.': '.$partPublicShareUrl) }}"
            target="_blank"
            rel="noopener"
            class="pe-part-action-btn"
            title="Compartilhar no WhatsApp"
        >
            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.288.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                <path d="M12.004 2c-5.514 0-9.99 4.476-9.99 9.99 0 1.76.464 3.484 1.346 5.001L2 22l5.135-1.342a9.96 9.96 0 0 0 4.869 1.242h.004c5.514 0 9.99-4.476 9.99-9.99 0-2.669-1.04-5.176-2.928-7.062A9.935 9.935 0 0 0 12.004 2zm0 18.156a8.15 8.15 0 0 1-4.157-1.137l-.298-.177-3.048.797.813-2.97-.194-.306a8.135 8.135 0 0 1-1.257-4.373c0-4.502 3.664-8.166 8.171-8.166a8.12 8.12 0 0 1 5.775 2.393 8.107 8.107 0 0 1 2.392 5.775c-.004 4.507-3.668 8.164-8.197 8.164z"/>
            </svg>
        </a>

        <span x-show="copied" x-cloak class="pe-part-action-copied">Link copiado!</span>
    </div>
</div>
