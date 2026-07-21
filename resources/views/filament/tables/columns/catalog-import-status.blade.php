@php
    $catalog = $getRecord();
@endphp

@if ($catalog->import_status === \App\Models\Catalog\Enums\ImportStatus::Importing)
    <div style="display: flex; justify-content: center; width: 100%;" title="Importando">
        <x-filament::icon
            icon="heroicon-o-arrow-path"
            style="width: 1.25rem; height: 1.25rem; color: #3b82f6; animation: spin 2.5s linear infinite;"
        />
    </div>
@elseif ($catalog->import_status === \App\Models\Catalog\Enums\ImportStatus::Imported)
    <div style="display: flex; justify-content: center; width: 100%;" title="Já importado">
        <x-filament::icon
            icon="heroicon-o-check-circle"
            style="width: 1.25rem; height: 1.25rem; color: #22c55e;"
        />
    </div>
@else
    <div style="display: flex; justify-content: center; width: 100%;" title="Não importado">
        <x-filament::icon
            icon="heroicon-o-minus-circle"
            style="width: 1.25rem; height: 1.25rem; color: #9ca3af;"
        />
    </div>
@endif
