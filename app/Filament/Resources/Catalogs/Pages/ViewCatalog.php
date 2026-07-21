<?php

namespace App\Filament\Resources\Catalogs\Pages;

use App\Filament\Resources\Catalogs\CatalogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCatalog extends ViewRecord
{
    protected static string $resource = CatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
