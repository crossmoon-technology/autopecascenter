<?php

namespace App\Filament\Pages\Buscas;

use App\Models\Manufacturer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class Iframes extends Page
{
    protected string $view = 'filament.pages.buscas.iframes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Iframes';

    protected static ?string $title = 'Iframes';

    /**
     * @var array<int, bool>
     */
    public array $manufacturers = [];

    public function mount(): void
    {
        $this->manufacturers = $this->manufacturersWithIframe()
            ->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function manufacturersWithIframe(): Collection
    {
        return Manufacturer::query()
            ->where('is_active', true)
            ->whereNotNull('iframe_url')
            ->orderBy('name')
            ->get();
    }
}
