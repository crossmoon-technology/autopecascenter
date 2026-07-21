<x-filament-panels::page>
    <style>
        .manufacturers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
        }

        .manufacturer-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1.5rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #fff;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.15s ease, transform 0.15s ease;
        }

        .manufacturer-card[href]:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .manufacturer-card__logo {
            height: 4rem;
            width: 100%;
            object-fit: contain;
        }

        .manufacturer-card__name {
            font-size: 0.875rem;
            font-weight: 500;
            text-align: center;
        }

        @media (prefers-color-scheme: dark) {
            .manufacturer-card {
                background-color: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
            }
        }
    </style>

    <div class="manufacturers-grid">
        @foreach ($this->getManufacturers() as $manufacturer)
            <a
                @if ($manufacturer->external_link)
                    href="{{ $manufacturer->external_link }}"
                    target="_blank"
                    rel="noopener"
                @endif
                class="manufacturer-card"
            >
                @if ($manufacturer->logo)
                    <img
                        src="{{ Storage::disk('public')->url($manufacturer->logo) }}"
                        alt="{{ $manufacturer->name }}"
                        class="manufacturer-card__logo"
                    />
                @endif

                <span class="manufacturer-card__name">{{ $manufacturer->name }}</span>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
