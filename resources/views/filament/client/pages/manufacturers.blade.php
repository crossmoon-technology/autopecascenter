<x-filament-panels::page>
    <style>
        .cm-manufacturers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
            gap: 1rem;
        }
        .cm-manufacturer-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            aspect-ratio: 1 / 1;
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 2px solid rgb(249 70 3);
            background: rgba(249, 70, 3, 0.08);
            color: inherit;
        }
        .cm-manufacturer-card-icon {
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
        .cm-manufacturer-card-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .cm-manufacturer-card-icon-placeholder {
            background: rgba(127, 127, 127, 0.18);
        }
        .cm-manufacturer-card-name {
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
        .cm-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
    </style>

    @php $manufacturers = $this->getManufacturers(); @endphp

    @if ($manufacturers->isEmpty())
        <p class="cm-empty">Nenhum fabricante disponível.</p>
    @else
        <div class="cm-manufacturers-grid">
            @foreach ($manufacturers as $manufacturer)
                @php $manufacturerImage = $manufacturer->icon ?? $manufacturer->logo; @endphp

                <div class="cm-manufacturer-card">
                    @if ($manufacturerImage)
                        <span class="cm-manufacturer-card-icon">
                            <img
                                src="{{ Storage::disk('public')->url($manufacturerImage) }}"
                                alt=""
                            >
                        </span>
                    @else
                        <span class="cm-manufacturer-card-icon cm-manufacturer-card-icon-placeholder"></span>
                    @endif

                    <span class="cm-manufacturer-card-name">{{ $manufacturer->name }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
