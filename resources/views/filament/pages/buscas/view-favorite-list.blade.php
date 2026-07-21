<x-filament-panels::page>
    <a href="{{ \App\Filament\Pages\Buscas\Favorites::getUrl() }}" style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; opacity: 0.65; text-decoration: none; color: inherit; margin-bottom: 1rem;">
        &larr; Todas as listas
    </a>

    {{ $this->table }}
</x-filament-panels::page>
