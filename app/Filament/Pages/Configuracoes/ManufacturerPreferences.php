<?php

namespace App\Filament\Pages\Configuracoes;

use App\Models\Manufacturer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManufacturerPreferences extends Page
{
    protected string $view = 'filament.pages.configuracoes.manufacturer-preferences';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Fabricantes habilitados';

    protected static ?string $title = 'Fabricantes habilitados';

    /**
     * @var array<int, bool>
     */
    public array $manufacturers = [];

    public function mount(): void
    {
        $preferred_ids = Auth::user()->preferredManufacturers()->pluck('manufacturers.id')->all();

        $this->manufacturers = $this->availableManufacturers()
            ->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => in_array($id, $preferred_ids, true)])
            ->all();
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function availableManufacturers(): Collection
    {
        return Manufacturer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $selected_ids = collect($this->manufacturers)
            ->filter()
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        Auth::user()->preferredManufacturers()->sync($selected_ids);

        Notification::make()
            ->title('Preferências salvas.')
            ->body('Suas buscas já abrem com esses fabricantes selecionados.')
            ->success()
            ->send();
    }
}
