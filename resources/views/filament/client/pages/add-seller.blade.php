<x-filament-panels::page>
    <style>
        .as-submit {
            margin-top: 1rem;
        }
        .as-sellers {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .as-seller {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 127, 127, 0.2);
        }
        .as-seller-name {
            font-weight: 600;
        }
        .as-seller-email {
            font-size: 0.8125rem;
            opacity: 0.7;
        }
        .as-empty {
            font-size: 0.875rem;
            opacity: 0.65;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Vendedores vinculados</x-slot>

        @php $sellers = $this->sellers(); @endphp

        @if ($sellers->isEmpty())
            <p class="as-empty">Nenhum vendedor vinculado ainda.</p>
        @else
            <div class="as-sellers">
                @foreach ($sellers as $seller)
                    <div class="as-seller">
                        <div>
                            <div class="as-seller-name">{{ $seller->name }}</div>
                            <div class="as-seller-email">{{ $seller->email }}</div>
                        </div>

                        <x-filament::button
                            color="danger"
                            size="sm"
                            wire:click="removeSeller({{ $seller->id }})"
                            wire:confirm="Desanexar {{ $seller->name }}? Você não vai mais poder criar pedidos pra ele até se vincular de novo."
                        >
                            Desanexar
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Adicionar vendedor</x-slot>

        <form wire:submit="addSeller">
            {{ $this->form }}

            <x-filament::button type="submit" class="as-submit">
                Adicionar vendedor
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
