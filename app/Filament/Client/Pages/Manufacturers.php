<?php

namespace App\Filament\Client\Pages;

use App\Models\Manufacturer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class Manufacturers extends Page
{
    protected string $view = 'filament.client.pages.manufacturers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Fabricantes';

    protected static ?string $title = 'Fabricantes';

    public function getManufacturers(): Collection
    {
        return Manufacturer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
