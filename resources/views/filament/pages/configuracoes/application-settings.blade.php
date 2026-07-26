<x-filament-panels::page>
    <style>
        .as-save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1.25rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background-color: rgb(249 70 3);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .as-save-button:hover {
            background-color: rgb(199 56 2);
        }
    </style>

    <form wire:submit="save" class="fi-form space-y-6">
        {{ $this->form }}

        <button type="submit" class="as-save-button">
            Salvar
        </button>
    </form>
</x-filament-panels::page>
