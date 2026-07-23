<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Pages\Concerns\ScopesManufacturersToInvitingSeller;
use App\Models\Manufacturer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class Manufacturers extends Page
{
    use ScopesManufacturersToInvitingSeller;

    protected string $view = 'filament.client.pages.manufacturers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $navigationLabel = 'Fabricantes';

    protected static ?string $title = 'Fabricantes';

    /**
     * @return Collection<int, Manufacturer>
     */
    public function getManufacturers(): Collection
    {
        return $this->manufacturersScopedToInvitingSeller();
    }
}
