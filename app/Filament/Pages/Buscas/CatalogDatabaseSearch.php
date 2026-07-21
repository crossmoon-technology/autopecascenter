<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Pages\Buscas\CatalogDatabaseSearch\Enums\SearchType;
use App\Filament\Pages\Buscas\Concerns\AddsToQuotation;
use App\Filament\Pages\Buscas\Concerns\ManagesFavoriteLists;
use App\Filament\Pages\Buscas\Concerns\RecordsSearchHistory;
use App\Filament\Pages\Buscas\Concerns\ResolvesPreferredManufacturers;
use App\Models\Manufacturer;
use App\Models\Part;
use App\Models\SearchHistory\Enums\Method;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use UnitEnum;

class CatalogDatabaseSearch extends Page implements HasActions, HasForms
{
    use AddsToQuotation;
    use InteractsWithActions;
    use InteractsWithForms;
    use ManagesFavoriteLists;
    use RecordsSearchHistory;
    use ResolvesPreferredManufacturers;

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

    private const int MAX_RESULTS_PER_MANUFACTURER = 50;

    /**
     * A partir de quantas buscas pelo mesmo termo (nos últimos 30 dias) a gente sugere
     * favoritar em vez de deixar o usuário repetir a busca pra sempre.
     */
    private const int FAVORITE_SUGGESTION_THRESHOLD = 3;

    /**
     * @var array<int, Collection<int, Part>>
     */
    public array $results = [];

    /**
     * @var array<int>
     */
    public array $favoritedPartIds = [];

    /**
     * @var array<int>
     */
    public array $quotedPartIds = [];

    public function mount(): void
    {
        // fill() sem argumentos hidrata os defaults de todos os campos (ex: tipo_busca)
        // — passar um array parcial pra ele pula essa hidratação nos campos ausentes.
        $this->form->fill();

        // Vindo do Histórico, o código chega via query string pra pré-preencher o campo
        // sem já disparar a busca — o usuário confirma clicando em "Buscar".
        if (filled($codigo = request()->query('codigo'))) {
            $this->data['codigo'] = $codigo;
        }

        $this->data['manufacturers'] = $this->defaultManufacturerSelection($this->activeManufacturers());

        $this->favoritedPartIds = Auth::user()->favoritedPartIds()->all();
        $this->quotedPartIds = $this->currentlyQuotedPartIds();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->components([
                        Select::make('tipo_busca')
                            ->label('Tipo de busca')
                            ->options(SearchType::class)
                            ->default(SearchType::Todos)
                            ->required(),
                        TextInput::make('codigo')
                            ->label('Termo de busca')
                            ->placeholder('Ex: 16088')
                            ->required()
                            ->autofocus()
                            ->extraInputAttributes(['id' => 'busca-termo-input']),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function activeManufacturers(): Collection
    {
        $eligible = Manufacturer::query()
            ->where('is_active', true)
            ->whereHas('catalogs', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return $this->filterToEnabledManufacturers($eligible);
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

        $state = $this->form->getState();
        $codigo = trim($state['codigo']);
        $tipoBusca = $state['tipo_busca'];

        $this->hasSearched = true;

        $needle = '%'.strtolower($codigo).'%';
        $results = [];

        foreach ($this->activeManufacturers()->whereIn('id', $this->selectedManufacturerIds()) as $manufacturer) {
            $results[$manufacturer->id] = Part::query()
                ->whereHas('catalog', fn ($query) => $query
                    ->where('manufacturer_id', $manufacturer->id)
                    ->where('is_active', true))
                ->where(fn (Builder $query) => match ($tipoBusca) {
                    SearchType::Todos => $query->whereRaw('LOWER(codigo) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(conversoes AS TEXT)) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(CAST(atributos AS TEXT)) LIKE ?', [$needle]),
                    SearchType::Codigo => $query->whereRaw('LOWER(codigo) LIKE ?', [$needle]),
                    SearchType::Equivalentes => $query->whereRaw('LOWER(CAST(conversoes AS TEXT)) LIKE ?', [$needle]),
                    SearchType::Atributos => $query->whereRaw('LOWER(CAST(atributos AS TEXT)) LIKE ?', [$needle]),
                })
                ->orderBy('codigo')
                ->limit(self::MAX_RESULTS_PER_MANUFACTURER)
                ->get();
        }

        $this->results = $results;

        $foundResults = collect($results)->flatten(1)->isNotEmpty();
        $this->recordSearchHistory($codigo, Method::Database, $foundResults);

        $this->suggestFavoritingIfSearchedRepeatedly($codigo);
    }

    /**
     * Se o usuário já buscou esse mesmo termo várias vezes recentemente e a peça
     * encontrada ainda não está favoritada, sugere favoritar em vez de deixar ele
     * repetir a busca pra sempre.
     */
    private function suggestFavoritingIfSearchedRepeatedly(string $codigo): void
    {
        $searchCount = Auth::user()->searchHistory()
            ->where('method', Method::Database)
            ->whereRaw('LOWER(query) = ?', [strtolower($codigo)])
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($searchCount < self::FAVORITE_SUGGESTION_THRESHOLD) {
            return;
        }

        $foundPartIds = collect($this->results)->flatten(1)->pluck('id');

        if ($foundPartIds->isEmpty() || $foundPartIds->intersect($this->favoritedPartIds)->isNotEmpty()) {
            return;
        }

        $suggestedPartId = $foundPartIds->first();

        Notification::make()
            ->title("Você já buscou \"{$codigo}\" {$searchCount} vezes")
            ->body('Quer favoritar essa peça pra não precisar buscar de novo?')
            ->info()
            ->actions([
                Action::make('favoritar')
                    ->button()
                    ->dispatch('favoritePart', [$suggestedPartId]),
            ])
            ->send();
    }

    #[On('favoritePart')]
    public function favoritePartFromSuggestion(int $part_id): void
    {
        if (! in_array($part_id, $this->favoritedPartIds, true)) {
            $this->toggleFavorite($part_id);
        }
    }

    /**
     * Atributos vem de jsonl arbitrário por fabricante, então um valor pode
     * ser uma string, um array simples, ou (raramente) um array aninhado —
     * formata recursivamente em vez de assumir uma estrutura fixa.
     */
    public function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => $this->formatValue($item))->implode(', ');
        }

        return (string) $value;
    }

    /**
     * Favoritar é exclusivo da Base de dados: os resultados da API são DTOs de
     * scraping sem um registro estável no banco pra pendurar o favorito.
     *
     * Toggle rápido: desfavoritar tira a peça de TODAS as listas do usuário; favoritar
     * manda pra "Lista padrão" (quem quiser uma lista específica usa a action ao lado,
     * ver pickFavoriteListAction em ManagesFavoriteLists).
     */
    public function toggleFavorite(int $part_id): void
    {
        $user = Auth::user();

        if (in_array($part_id, $this->favoritedPartIds, true)) {
            $user->detachPartFromAllFavoriteLists($part_id);
            $this->favoritedPartIds = array_values(array_diff($this->favoritedPartIds, [$part_id]));

            return;
        }

        $user->defaultFavoriteList()->parts()->syncWithoutDetaching([$part_id]);
        $this->favoritedPartIds[] = $part_id;
    }

    protected function markPartAsFavorited(int $part_id): void
    {
        if (! in_array($part_id, $this->favoritedPartIds, true)) {
            $this->favoritedPartIds[] = $part_id;
        }
    }

    public function partViewUrl(Part $part): string
    {
        return ViewPart::getUrl(['record' => $part->id]);
    }

    public function addToQuotation(int $part_id): void
    {
        $part = Part::query()->findOrFail($part_id);
        $isNowQuoted = $this->togglePartInQuotation($part);

        if ($isNowQuoted) {
            $this->quotedPartIds[] = $part_id;
        } else {
            $this->quotedPartIds = array_values(array_diff($this->quotedPartIds, [$part_id]));
        }
    }
}
