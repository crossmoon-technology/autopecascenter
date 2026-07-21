<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Pages\Buscas\Api\Enums\SearchStatus;
use App\Filament\Pages\Buscas\Concerns\AddsToQuotation;
use App\Filament\Pages\Buscas\Concerns\RecordsSearchHistory;
use App\Filament\Pages\Buscas\Concerns\ResolvesPreferredManufacturers;
use App\Models\Manufacturer;
use App\Models\QuotationItem\Enums\Source;
use App\Models\SearchHistory\Enums\Method;
use App\Services\PartSearch\PartSearchProviderRegistry;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

class Api extends Page implements HasForms
{
    use AddsToQuotation;
    use InteractsWithForms;
    use RecordsSearchHistory;
    use ResolvesPreferredManufacturers;

    protected string $view = 'filament.pages.buscas.api';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'API';

    protected static ?string $title = 'API';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public bool $searched = false;

    public string $activeQuery = '';

    /**
     * @var array<int, array{manufacturer: Manufacturer, results: Collection, status: SearchStatus}>
     */
    public array $results = [];

    /**
     * @var array<string>
     */
    public array $quotedApiKeys = [];

    public function mount(): void
    {
        // fill() sem argumentos hidrata os defaults de todos os campos — passar um
        // array parcial pra ele pula essa hidratação nos campos ausentes.
        $this->form->fill();

        // Vindo do Histórico, o código chega via query string pra pré-preencher o campo
        // sem já disparar a busca — o usuário confirma clicando em "Buscar".
        if (filled($codigo = request()->query('codigo'))) {
            $this->data['codigo'] = $codigo;
        }

        $this->data['manufacturers'] = $this->defaultManufacturerSelection($this->searchableManufacturers());

        $this->quotedApiKeys = $this->currentlyQuotedExternalKeys(Source::Api);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->label('Código da peça')
                    ->placeholder('Ex: 16002')
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes(['id' => 'busca-termo-input']),
            ])
            ->statePath('data');
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

    /**
     * Sets every selected manufacturer's tab to "loading" and returns their ids in
     * display order. The browser then calls searchManufacturer() once per id, one at
     * a time, so each tab's icon and results populate as soon as that supplier
     * responds instead of the whole page blocking on the slowest one.
     *
     * @return array<int>
     */
    public function search(): array
    {
        if (empty($this->selectedManufacturerIds())) {
            Notification::make()
                ->title('Selecione ao menos um fabricante para buscar.')
                ->warning()
                ->send();

            return [];
        }

        $state = $this->form->getState();

        $this->activeQuery = trim($state['codigo'] ?? '');
        $this->searched = true;

        $this->recordSearchHistory($this->activeQuery, Method::Api);

        $selectedIds = $this->selectedManufacturerIds();
        $manufacturers = $this->searchableManufacturers()->whereIn('id', $selectedIds);

        $results = [];

        foreach ($manufacturers as $manufacturer) {
            $results[$manufacturer->id] = [
                'manufacturer' => $manufacturer,
                'results' => collect(),
                'status' => SearchStatus::Loading,
            ];
        }

        $this->results = $results;

        return $manufacturers->pluck('id')->all();
    }

    public function searchManufacturer(int $manufacturer_id): void
    {
        $manufacturer = $this->results[$manufacturer_id]['manufacturer'] ?? null;

        if (! $manufacturer) {
            return;
        }

        $provider = app(PartSearchProviderRegistry::class)->for($manufacturer);

        try {
            $this->results[$manufacturer_id]['results'] = $provider->search($this->activeQuery);
            $this->results[$manufacturer_id]['status'] = SearchStatus::Success;
        } catch (Throwable $exception) {
            report($exception);
            $this->results[$manufacturer_id]['status'] = SearchStatus::Failed;
        }
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function searchableManufacturers(): Collection
    {
        $registry = app(PartSearchProviderRegistry::class);

        $eligible = Manufacturer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Manufacturer $manufacturer) => $registry->for($manufacturer) !== null)
            ->values();

        return $this->filterToEnabledManufacturers($eligible);
    }

    public function itemShareUrl(string $codigo): string
    {
        return static::getUrl(['codigo' => $codigo]);
    }

    public function addToQuotation(int $manufacturer_id, string $codigo, ?string $descricao = null): void
    {
        $key = "{$manufacturer_id}|{$codigo}";
        $isNowQuoted = $this->toggleExternalItemInQuotation(Source::Api, $manufacturer_id, $codigo, $descricao);

        if ($isNowQuoted) {
            $this->quotedApiKeys[] = $key;
        } else {
            $this->quotedApiKeys = array_values(array_diff($this->quotedApiKeys, [$key]));
        }
    }
}
