<x-filament-panels::page>
    <form wire:submit="search">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Buscar
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        A busca por equivalências ainda não foi implementada.
    </div>
</x-filament-panels::page>
