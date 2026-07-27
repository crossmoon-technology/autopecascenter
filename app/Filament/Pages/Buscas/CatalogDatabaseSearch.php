<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Concerns\HasHelpAction;
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
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use UnitEnum;

class CatalogDatabaseSearch extends Page implements HasActions, HasForms
{
    use AddsToQuotation;
    use HasHelpAction;
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

    /**
     * O termo efetivamente buscado — não $data['codigo'] direto, porque esse reflete o
     * campo do formulário AO VIVO (pode já ter sido editado sem re-buscar); esse aqui só
     * muda quando uma busca de fato roda, garantindo que o destaque nos resultados
     * (ver highlight()) sempre corresponda ao que realmente foi buscado.
     */
    public string $searchedCodigo = '';

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
     * Resultado da seção "Peças exatas" (código próprio + cadeia de conversão via
     * App\Services\PartEquivalence\RebuildPartEquivalences), agrupado por fabricante —
     * diferente de $results, essa seção varre TODOS os fabricantes ativos sempre,
     * ignorando o checkbox de fabricantes selecionados (ver activeManufacturers()).
     * A ideia é achar peças mesmo de fabricantes que o vendedor nem pensaria em marcar.
     *
     * @var array<int, Collection<int, Part>>
     */
    public array $exactResults = [];

    /**
     * true quando o índice pré-computado (part_reference_codes) não tinha nada pro
     * termo buscado e foi preciso recorrer ao scan ao vivo — só acontece pra peças
     * ainda não indexadas (ver App\Console\Commands\RebuildPartEquivalences).
     */
    public bool $usedFallbackScan = false;

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

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a Base de dados';
    }

    protected function helpDescription(): string
    {
        return '<p>Aqui a busca é feita direto nos catálogos e peças já cadastrados na nossa base — escolha o tipo de busca (código, equivalentes ou atributos), os fabricantes e digite o termo.</p>'.
            '<p>Os resultados aparecem em duas seções: "Resultados" respeita os fabricantes marcados acima. Já "Peças exatas" busca o código próprio em TODOS os fabricantes ativos, ignorando a marcação — pra achar peças mesmo de um fabricante que o cliente trouxe e você nem pensaria em marcar. Se a peça encontrada tiver códigos de conversão pra outras marcas, essas peças equivalentes também aparecem juntas ali.</p>'.
            '<p>Os resultados aparecem na hora. Dá pra favoritar uma peça (estrela) ou adicionar direto à cotação, sem sair da página.</p>';
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
        $state = $this->form->getState();

        $this->runSearch(trim($state['codigo']), $this->resolveSearchType($state['tipo_busca']));
    }

    /**
     * Clicar num código de equivalência nos resultados de "Peças exatas" chama isso —
     * atualiza o campo pro código clicado e já refaz a busca com ele, sem sair da
     * página. Mantém o tipo de busca que já estava selecionado.
     */
    public function searchFor(string $codigo): void
    {
        $this->data['codigo'] = $codigo;

        $this->runSearch(trim($codigo), $this->resolveSearchType($this->data['tipo_busca'] ?? null));
    }

    /**
     * O state do Select::options(SearchType::class) já vem como instância do enum via
     * $this->form->getState(), mas $this->data['tipo_busca'] lido fora do ciclo normal
     * do form (como em searchFor()) pode estar como string crua — aceita os dois formatos.
     */
    private function resolveSearchType(mixed $value): SearchType
    {
        if ($value instanceof SearchType) {
            return $value;
        }

        return SearchType::tryFrom((string) $value) ?? SearchType::Todos;
    }

    private function runSearch(string $codigo, SearchType $tipoBusca): void
    {
        $this->hasSearched = true;
        $this->searchedCodigo = $codigo;

        if (empty($this->selectedManufacturerIds())) {
            Notification::make()
                ->title('Selecione ao menos um fabricante para ver os resultados por conversão/atributos.')
                ->warning()
                ->send();

            $this->results = [];
        } else {
            // Atributos é texto livre (descrição, montadora...) — continua comparado em
            // minúsculo, sem mexer em espaço, já que espaço faz parte do sentido de uma
            // frase. Código e conversoes são CÓDIGOS — normalizados (maiúsculo, sem
            // espaço) nos dois lados da comparação, pra bater com o que é salvo (ver
            // Part::booted()) mesmo que o vendedor digite com espaço/minúsculo.
            $attributeNeedle = '%'.strtolower($codigo).'%';
            $codeNeedle = '%'.Part::normalizeCode($codigo).'%';
            $results = [];

            foreach ($this->activeManufacturers()->whereIn('id', $this->selectedManufacturerIds()) as $manufacturer) {
                $results[$manufacturer->id] = Part::query()
                    ->whereHas('catalog', fn ($query) => $query
                        ->where('manufacturer_id', $manufacturer->id)
                        ->where('is_active', true))
                    ->where(fn (Builder $query) => match ($tipoBusca) {
                        SearchType::Todos => $query->whereRaw("UPPER(REPLACE(codigo, ' ', '')) LIKE ?", [$codeNeedle])
                            ->orWhereRaw("UPPER(REPLACE(CAST(conversoes AS TEXT), ' ', '')) LIKE ?", [$codeNeedle])
                            ->orWhereRaw('LOWER(CAST(atributos AS TEXT)) LIKE ?', [$attributeNeedle]),
                        SearchType::Codigo => $query->whereRaw("UPPER(REPLACE(codigo, ' ', '')) LIKE ?", [$codeNeedle]),
                        SearchType::Equivalentes => $query->whereRaw("UPPER(REPLACE(CAST(conversoes AS TEXT), ' ', '')) LIKE ?", [$codeNeedle]),
                        SearchType::Atributos => $query->whereRaw('LOWER(CAST(atributos AS TEXT)) LIKE ?', [$attributeNeedle]),
                    })
                    ->orderBy('codigo')
                    ->limit(self::MAX_RESULTS_PER_MANUFACTURER)
                    ->get();
            }

            $this->results = $results;
        }

        // "Peças exatas" ignora o checkbox de fabricantes de propósito — o vendedor pode
        // ter recebido o código de um fabricante que nem pensaria em marcar.
        $this->computeExactResults($codigo);

        $foundResults = collect($this->results)->flatten(1)->isNotEmpty()
            || collect($this->exactResults)->flatten(1)->isNotEmpty();
        $this->recordSearchHistory($codigo, Method::Database, $foundResults);

        $this->suggestFavoritingIfSearchedRepeatedly($codigo);
    }

    /**
     * "Peças exatas": tenta primeiro o índice pré-computado
     * (App\Services\PartEquivalence\RebuildPartEquivalences), igualdade indexada; só cai
     * pro scan ao vivo (mais lento, mesma exatidão) se o índice não achar nada — o que só
     * acontece pra peças importadas antes dessa funcionalidade existir e ainda não
     * passaram pelo backfill (ver App\Console\Commands\RebuildPartEquivalences).
     * "GH 123" não traz "GH 1234" aqui — busca parcial já é coberta pelo tipo de busca
     * "Código" lá em cima.
     *
     * Cada peça encontrada pelo código próprio também traz consigo TODAS as peças que
     * ela referencia como equivalentes (cadeia pré-computada, cobrindo todo código de
     * conversão dela, não só um) — é assim que um código PRÓPRIO de um fabricante que a
     * gente nem pesquisou diretamente ainda revela peças vendáveis de outros fabricantes.
     */
    private function computeExactResults(string $codigo): void
    {
        $normalized = Part::normalizeCode($codigo);
        $this->usedFallbackScan = false;

        $directMatches = $this->exactMatchesViaIndex($normalized);

        if ($directMatches->isEmpty()) {
            $directMatches = $this->exactMatchesViaLiveScan($normalized);

            // Só é sinal de índice desatualizado se o scan ao vivo achou algo que o
            // índice não tinha — se os dois vieram vazios, o código simplesmente não
            // existe em nenhuma peça, que é um resultado normal, não um problema de dado.
            if ($directMatches->isNotEmpty()) {
                $this->usedFallbackScan = true;
            }
        }

        $chained = $this->chainedEquivalentsOf($directMatches);

        $this->exactResults = $directMatches->concat($chained)
            ->unique('id')
            ->groupBy(fn (Part $part): int => $part->catalog->manufacturer_id)
            ->map(fn (Collection $group): Collection => $group->values())
            ->all();
    }

    /**
     * Todos os fabricantes ativos e habilitados (ver activeManufacturers()) — não
     * filtrado pelo checkbox marcado na tela, de propósito, só pra essa seção.
     */
    private function allActivePartsQuery(): Builder
    {
        $manufacturerIds = $this->activeManufacturers()->pluck('id');

        return Part::query()
            ->whereHas('catalog', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('manufacturer_id', $manufacturerIds))
            ->with('catalog.manufacturer');
    }

    /**
     * @return Collection<int, Part>
     */
    private function exactMatchesViaIndex(string $normalized): Collection
    {
        $partIds = DB::table('part_reference_codes')->where('token', $normalized)->pluck('part_id')->unique();

        if ($partIds->isEmpty()) {
            return new Collection;
        }

        return $this->allActivePartsQuery()
            ->whereIn('id', $partIds)
            ->orderBy('codigo')
            ->get()
            // Um token pode bater com o código próprio OU com uma conversão da peça —
            // "Peças exatas" só quer o primeiro caso (o outro já é coberto pelo tipo de
            // busca "Equivalentes" lá em cima).
            ->filter(fn (Part $part): bool => Part::normalizeCode($part->codigo) === $normalized)
            ->values();
    }

    /**
     * Rede de segurança pra peças ainda não indexadas — usa LIKE só pra reduzir os
     * candidatos vindos do banco, mas a comparação que decide se entra no resultado é
     * sempre exata, em PHP — nunca substring, senão "GH 123" traria "GH 1234" junto.
     *
     * @return Collection<int, Part>
     */
    private function exactMatchesViaLiveScan(string $normalized): Collection
    {
        $needle = '%'.$normalized.'%';

        return $this->allActivePartsQuery()
            ->whereRaw("UPPER(REPLACE(codigo, ' ', '')) LIKE ?", [$needle])
            ->orderBy('codigo')
            ->get()
            ->filter(fn (Part $part): bool => Part::normalizeCode($part->codigo) === $normalized)
            ->values();
    }

    /**
     * @param  Collection<int, Part>  $directMatches
     * @return Collection<int, Part>
     */
    private function chainedEquivalentsOf(Collection $directMatches): Collection
    {
        if ($directMatches->isEmpty()) {
            return new Collection;
        }

        $directIds = $directMatches->pluck('id');

        $chainedIds = DB::table('part_equivalences')
            ->whereIn('part_id', $directIds)
            ->pluck('equivalent_part_id')
            ->unique();

        if ($chainedIds->isEmpty()) {
            return new Collection;
        }

        return $this->allActivePartsQuery()
            ->whereIn('id', $chainedIds)
            ->whereNotIn('id', $directIds)
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Achata um valor de conversoes (string, array simples ou aninhado) numa lista de
     * códigos individuais, pra cada um virar seu próprio chip clicável na view — um
     * formatValue() concatenado não dá pra usar em wire:click separadamente por código.
     *
     * @return array<int, string>
     */
    public function equivalenceCodes(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->flatMap(fn ($item) => $this->equivalenceCodes($item))->all();
        }

        return [(string) $value];
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

        $foundPartIds = collect($this->results)->flatten(1)
            ->concat(collect($this->exactResults)->flatten(1))
            ->pluck('id');

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
     * "Marca-texto" do termo buscado dentro de um valor exibido no card (código,
     * atributo ou equivalência) — pra o vendedor ver exatamente qual pedaço do
     * resultado bateu com a busca, não só que a peça apareceu. Escapa o HTML primeiro
     * e só then insere a tag <mark>, pra não abrir brecha de XSS com dado de catálogo
     * (jsonl de fabricante é conteúdo externo, não confiável).
     */
    public function highlight(string $value): string
    {
        $stripped = preg_replace('/\s+/u', '', trim($this->searchedCodigo));

        if ($stripped === '' || $stripped === null) {
            return e($value);
        }

        $escapedValue = e($value);

        // Código agora é salvo sem espaço (ver Part::booted()), mas o termo digitado —
        // ou um valor exibido de dado legado ainda não reimportado — pode ter espaço em
        // qualquer posição. Um \s* opcional entre cada caractere do termo faz o destaque
        // bater nos dois sentidos, sem exigir que a pontuação de espaço seja idêntica.
        $pattern = collect(mb_str_split($stripped))
            ->map(fn (string $char): string => preg_quote(e($char), '/'))
            ->implode('\s*');

        return preg_replace("/({$pattern})/iu", '<mark class="pe-highlight">$1</mark>', $escapedValue);
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
