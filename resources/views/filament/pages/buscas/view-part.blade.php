<x-filament-panels::page>
    <style>
        .vp-back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            opacity: 0.65;
            text-decoration: none;
            color: inherit;
            margin-bottom: 1rem;
        }
        .vp-back-link:hover {
            opacity: 1;
        }
        .vp-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .vp-header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .vp-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 4rem;
            height: 4rem;
            flex-shrink: 0;
            border-radius: 0.75rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
            background: #fff;
            padding: 0.5rem;
            overflow: hidden;
        }
        .vp-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .vp-logo-placeholder {
            background: rgba(127, 127, 127, 0.15);
        }
        .vp-codigo {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .vp-meta {
            font-size: 0.875rem;
            opacity: 0.65;
            margin-top: 0.125rem;
        }
        .vp-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .vp-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: 2.25rem;
            height: 2.25rem;
            flex-shrink: 0;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.25);
            background: transparent;
            color: inherit;
            opacity: 0.7;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.15s ease, border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .vp-action-btn:hover {
            opacity: 1;
            border-color: rgb(249 70 3);
        }
        .vp-action-btn svg {
            width: 1.125rem;
            height: 1.125rem;
        }
        .vp-action-btn-favorited {
            opacity: 1;
            border-color: rgb(234 179 8);
            background: rgba(234, 179, 8, 0.12);
            color: rgb(161 98 7);
        }
        .vp-action-btn-quoted {
            opacity: 1;
            border-color: rgb(34 197 94);
            background: rgba(34, 197, 94, 0.12);
            color: rgb(21 128 61);
        }
        .vp-copied {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgb(21 128 61);
        }
        .vp-section {
            margin-top: 1.5rem;
        }
        .vp-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            opacity: 0.6;
            margin-bottom: 0.625rem;
        }
        .vp-attributes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
        }
        .vp-attribute {
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
        }
        .vp-attribute-label {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            opacity: 0.55;
            font-weight: 600;
        }
        .vp-attribute-value {
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 0.125rem;
        }
        .vp-equivalences {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .vp-equivalence {
            font-size: 0.875rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
        }
        .vp-equivalence strong {
            font-weight: 700;
        }
        .vp-share-panel {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }
        .vp-share-qr {
            flex-shrink: 0;
            padding: 0.5rem;
            border-radius: 0.625rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
            background: #fff;
        }
        .vp-share-qr svg {
            display: block;
            width: 96px;
            height: 96px;
        }
        .vp-share-info {
            font-size: 0.8125rem;
            opacity: 0.7;
            max-width: 26rem;
        }
        .vp-share-url {
            display: block;
            margin-top: 0.375rem;
            font-size: 0.75rem;
            font-family: ui-monospace, monospace;
            word-break: break-all;
            color: rgb(249 70 3);
            text-decoration: none;
        }
        .vp-share-url:hover {
            text-decoration: underline;
        }
    </style>

    <a href="{{ \App\Filament\Pages\Buscas\CatalogDatabaseSearch::getUrl(['codigo' => $part->codigo]) }}" class="vp-back-link">
        &larr; Voltar à busca
    </a>

    <x-filament::section>
        <div class="vp-header" x-data="{ copied: false }">
            <div class="vp-header-info">
                @php $logo = $part->catalog->manufacturer->icon ?? $part->catalog->manufacturer->logo; @endphp

                @if ($logo)
                    <span class="vp-logo">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="">
                    </span>
                @else
                    <span class="vp-logo vp-logo-placeholder"></span>
                @endif

                <div>
                    <div class="vp-codigo">{{ $part->codigo }}</div>
                    <div class="vp-meta">{{ $part->catalog->manufacturer->name }} &middot; {{ $part->catalog->name }}</div>
                </div>
            </div>

            <div class="vp-actions">
                <button
                    type="button"
                    wire:click="toggleFavorite"
                    class="vp-action-btn {{ $isFavorited ? 'vp-action-btn-favorited' : '' }}"
                    title="{{ $isFavorited ? 'Remover dos favoritos' : 'Favoritar' }}"
                >
                    <x-filament::icon :icon="$isFavorited ? 'heroicon-s-star' : 'heroicon-o-star'" />
                </button>

                <button
                    type="button"
                    wire:click="mountAction('pickFavoriteList', { part_id: {{ $part->id }} })"
                    class="vp-action-btn"
                    title="Adicionar a uma lista"
                >
                    <x-filament::icon icon="heroicon-o-folder-plus" />
                </button>

                <button
                    type="button"
                    wire:click="addToQuotation"
                    class="vp-action-btn {{ $isQuoted ? 'vp-action-btn-quoted' : '' }}"
                    title="{{ $isQuoted ? 'Remover da cotação' : 'Adicionar à cotação' }}"
                >
                    <x-filament::icon icon="heroicon-o-shopping-cart" />
                </button>

                <button
                    type="button"
                    class="vp-action-btn"
                    title="Copiar link"
                    x-on:click="navigator.clipboard.writeText('{{ $this->shareUrl() }}'); copied = true; setTimeout(() => copied = false, 1500)"
                >
                    <x-filament::icon icon="heroicon-o-link" />
                </button>

                <a
                    href="https://wa.me/?text={{ urlencode('Peça '.$part->codigo.': '.$this->shareUrl()) }}"
                    target="_blank"
                    rel="noopener"
                    class="vp-action-btn"
                    title="Compartilhar no WhatsApp"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.288.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12.004 2c-5.514 0-9.99 4.476-9.99 9.99 0 1.76.464 3.484 1.346 5.001L2 22l5.135-1.342a9.96 9.96 0 0 0 4.869 1.242h.004c5.514 0 9.99-4.476 9.99-9.99 0-2.669-1.04-5.176-2.928-7.062A9.935 9.935 0 0 0 12.004 2zm0 18.156a8.15 8.15 0 0 1-4.157-1.137l-.298-.177-3.048.797.813-2.97-.194-.306a8.135 8.135 0 0 1-1.257-4.373c0-4.502 3.664-8.166 8.171-8.166a8.12 8.12 0 0 1 5.775 2.393 8.107 8.107 0 0 1 2.392 5.775c-.004 4.507-3.668 8.164-8.197 8.164z"/>
                    </svg>
                </a>

                <span x-show="copied" x-cloak class="vp-copied">Link copiado!</span>
            </div>
        </div>
    </x-filament::section>

    <div class="vp-section">
        <x-filament::section>
            <x-slot name="heading">Compartilhar</x-slot>
            <x-slot name="description">
                Esse link funciona pra qualquer pessoa, mesmo sem conta no painel — pode
                mandar pro cliente ou fornecedor direto, ou escanear o QR Code no balcão.
            </x-slot>

            @php $shareUrl = $this->shareUrl(); @endphp

            <div class="vp-share-panel">
                <span class="vp-share-qr">{!! $this->shareQrCodeSvg() !!}</span>
                <div class="vp-share-info">
                    Link válido por 90 dias.
                    <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="vp-share-url">{{ $shareUrl }}</a>
                </div>
            </div>
        </x-filament::section>
    </div>

    @if (! empty($part->atributos))
        <div class="vp-section">
            <div class="vp-section-title">Atributos</div>
            <div class="vp-attributes">
                @foreach ($part->atributos as $chave => $valor)
                    <div class="vp-attribute">
                        <div class="vp-attribute-label">{{ $chave }}</div>
                        <div class="vp-attribute-value">{{ $this->formatValue($valor) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (! empty($part->conversoes))
        <div class="vp-section">
            <div class="vp-section-title">Equivalências</div>
            <div class="vp-equivalences">
                @foreach ($part->conversoes as $marca => $codigos)
                    <div class="vp-equivalence"><strong>{{ $marca }}:</strong> {{ $this->formatValue($codigos) }}</div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
