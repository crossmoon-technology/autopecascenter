<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Pages\Buscas\Concerns\AddsToQuotation;
use App\Filament\Pages\Buscas\Concerns\ResolvesPreferredManufacturers;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Part;
use App\Models\QuotationItem;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Pipeline guiado: percorre cada item de um pedido, um de cada vez, deixando o vendedor
 * buscar e escolher a peça real que entra na cotação (ver AddsToQuotation — vai pro
 * mesmo carrinho/cotação em aberto de sempre) antes de avançar pro próximo item.
 */
class GuidedQuotation extends Page implements HasForms
{
    use AddsToQuotation;
    use InteractsWithForms;
    use ResolvesPreferredManufacturers;

    protected static ?string $slug = 'cotacao-guiada/{order}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.buscas.guided-quotation';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Vendas';

    private const int MAX_RESULTS_PER_MANUFACTURER = 50;

    public Order $currentOrder;

    public int $currentItemIndex = 0;

    public bool $hasSearched = false;

    public string $quotationName = '';

    /**
     * Só operacional (checkbox de "revisei e confirmo") — não é salvo em lugar nenhum,
     * só destrava o botão de salvar na tela de revisão.
     */
    public bool $reviewConfirmed = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * @var array<int, Collection<int, Part>>
     */
    public array $results = [];

    public function mount(int|string $order): void
    {
        $this->currentOrder = Order::query()
            ->whereHas('user', fn (Builder $query) => $query->where('invited_by_id', Auth::id()))
            ->with(['user', 'items.preferredManufacturers'])
            ->findOrFail($order);

        $this->form->fill();
        $this->prepareCurrentItem();
    }

    public function getTitle(): string
    {
        $clientName = $this->currentOrder->user?->name ?? 'cliente';

        return "Cotação guiada — {$clientName}";
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->label('Termo de busca')
                    ->placeholder('Ex: 16088')
                    ->required()
                    ->autofocus(),
            ])
            ->statePath('data');
    }

    public function currentItem(): ?OrderItem
    {
        return $this->currentOrder->items->get($this->currentItemIndex);
    }

    public function totalItems(): int
    {
        return $this->currentOrder->items->count();
    }

    public function isComplete(): bool
    {
        return $this->currentItemIndex >= $this->totalItems();
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function eligibleManufacturers(): Collection
    {
        $eligible = Manufacturer::query()
            ->where('is_active', true)
            ->whereHas('catalogs', fn (Builder $query) => $query->where('is_active', true))
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

    public function addAndContinue(int $part_id): void
    {
        $part = Part::query()->findOrFail($part_id);
        $quantity = $this->currentItem()?->quantity ?? 1;
        $this->addPartToQuotation($part, $quantity);

        $this->advance();
    }

    public function skipItem(): void
    {
        Notification::make()
            ->title('Item pulado.')
            ->send();

        $this->advance();
    }

    private function advance(): void
    {
        $this->currentItemIndex++;

        if (! $this->isComplete()) {
            $this->prepareCurrentItem();
        }
    }

    /**
     * @return Collection<int, QuotationItem>
     */
    public function reviewItems(): Collection
    {
        return Auth::user()->openQuotationOrNull()?->load('items.manufacturer')->items ?? new Collection;
    }

    public function removeReviewItem(int $item_id): void
    {
        Auth::user()->openQuotationOrNull()?->items()->whereKey($item_id)->delete();
    }

    /**
     * Salva a cotação montada durante o pipeline e já associa ao pedido — o vendedor não
     * precisa mais usar "Anexar cotação" separadamente depois (ver App\Filament\Pages\Buscas\Orders).
     */
    public function saveQuotation(): void
    {
        $quotation = Auth::user()->openQuotationOrNull();

        if (! $quotation || $quotation->items()->doesntExist()) {
            Notification::make()
                ->title('Adicione ao menos uma peça antes de salvar.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->reviewConfirmed) {
            Notification::make()
                ->title('Confirme que revisou os dados antes de salvar.')
                ->warning()
                ->send();

            return;
        }

        $quotation->close($this->quotationName);

        $this->currentOrder->update(['quotation_id' => $quotation->id]);

        $this->dispatch('quotation-updated');
        $this->dispatch('order-status-updated');

        Notification::make()
            ->title('Cotação salva e associada ao pedido.')
            ->success()
            ->send();

        $this->redirect(Orders::getUrl());
    }

    /**
     * Pré-preenche a busca com a string original digitada pelo cliente pra esse item e,
     * se ele indicou fabricantes de preferência, já deixa só eles marcados — senão cai
     * no padrão do vendedor (ver ResolvesPreferredManufacturers::defaultManufacturerSelection).
     */
    private function prepareCurrentItem(): void
    {
        $this->hasSearched = false;
        $this->results = [];

        $item = $this->currentItem();

        if (! $item) {
            return;
        }

        $eligible = $this->eligibleManufacturers();
        $itemPreferredIds = $item->preferredManufacturers->pluck('id');
        $eligiblePreferredIds = $eligible->pluck('id')->intersect($itemPreferredIds);

        $this->data['codigo'] = $item->description;
        $this->data['manufacturers'] = $eligiblePreferredIds->isNotEmpty()
            ? $eligible->pluck('id')->mapWithKeys(fn (int $id): array => [$id => $eligiblePreferredIds->contains($id)])->all()
            : $this->defaultManufacturerSelection($eligible);

        $this->search();
    }

    public function search(): void
    {
        if (empty($this->selectedManufacturerIds())) {
            return;
        }

        $state = $this->form->getState();
        $needle = '%'.strtolower(trim($state['codigo'])).'%';
        $results = [];

        foreach ($this->eligibleManufacturers()->whereIn('id', $this->selectedManufacturerIds()) as $manufacturer) {
            $results[$manufacturer->id] = Part::query()
                ->whereHas('catalog', fn (Builder $query) => $query
                    ->where('manufacturer_id', $manufacturer->id)
                    ->where('is_active', true))
                ->where(fn (Builder $query) => $query->whereRaw('LOWER(codigo) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(CAST(conversoes AS TEXT)) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(CAST(atributos AS TEXT)) LIKE ?', [$needle]))
                ->orderBy('codigo')
                ->limit(self::MAX_RESULTS_PER_MANUFACTURER)
                ->get();
        }

        $this->hasSearched = true;
        $this->results = $results;
    }

    /**
     * Atributos vem de jsonl arbitrário por fabricante, então um valor pode ser uma
     * string, um array simples, ou (raramente) um array aninhado — formata
     * recursivamente em vez de assumir uma estrutura fixa.
     */
    public function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => $this->formatValue($item))->implode(', ');
        }

        return (string) $value;
    }
}
