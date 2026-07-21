<x-filament-panels::page>
    <style>
        .gs-save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1.25rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background-color: rgb(37 99 235);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .gs-save-button:hover {
            background-color: rgb(29 78 216);
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Sua logo</x-slot>
        <x-slot name="description">
            Aparece ao lado da logo da Auto Peças Center no cabeçalho das cotações exportadas em PDF.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <button type="submit" class="gs-save-button">
                Salvar
            </button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
