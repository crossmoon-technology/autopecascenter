<?php

namespace App\Filament\Pages;

use App\Models\Manufacturer;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PartEquivalenceSearch extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.part-equivalence-search';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::MagnifyingGlass;

    protected static ?string $navigationLabel = 'Buscar Equivalência';

    protected static ?string $title = 'Buscar Equivalência';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('manufacturer_id')
                    ->label('Fabricante')
                    ->options(fn (): array => Manufacturer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('codigo')
                    ->label('Código da peça')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $this->form->getState();

        // TODO: implement the equivalence search.
    }
}
