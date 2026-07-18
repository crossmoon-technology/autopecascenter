<?php

namespace App\Filament\Pages\Buscas;

use App\Models\Manufacturer;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class CatalogDatabaseSearch extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.buscas.catalog-database-search';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::CircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Base de dados';

    protected static ?string $title = 'Base de dados';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public bool $hasSearched = false;

    public function mount(): void
    {
        $this->form->fill();

        $this->data['manufacturers'] = $this->activeManufacturers()
            ->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->label('Código da peça')
                    ->placeholder('Ex: 16088')
                    ->required()
                    ->autofocus(),
            ])
            ->statePath('data');
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function activeManufacturers(): Collection
    {
        return Manufacturer::query()
            ->where('is_active', true)
            ->whereHas('catalogs', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int>
     */
    public function selectedManufacturerIds(): array
    {
        return collect($this->data['manufacturers'] ?? [])
            ->filter()
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function search(): void
    {
        if (empty($this->selectedManufacturerIds())) {
            Notification::make()
                ->title('Selecione ao menos um fabricante para buscar.')
                ->warning()
                ->send();

            return;
        }

        $this->form->getState();

        $this->hasSearched = true;

        // TODO: implement the equivalence search itself — this only validates
        // the form and marks that a search was attempted.
    }
}
