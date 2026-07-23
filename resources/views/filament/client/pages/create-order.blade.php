<x-filament-panels::page>
    <style>
        .mo-submit {
            margin-top: 1rem;
        }
    </style>

    <x-filament::section>
        <form wire:submit="save">
            {{ $this->form }}

            <x-filament::button type="submit" class="mo-submit">
                Enviar pedido
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
