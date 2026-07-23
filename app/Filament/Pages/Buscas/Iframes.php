<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Pages\Buscas\Concerns\AddsToQuotation;
use App\Filament\Pages\Buscas\Concerns\ResolvesPreferredManufacturers;
use App\Models\Manufacturer;
use App\Models\QuotationItem\Enums\Source;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class Iframes extends Page
{
    use AddsToQuotation;
    use HasHelpAction;
    use ResolvesPreferredManufacturers;

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
        $this->manufacturers = $this->defaultManufacturerSelection($this->manufacturersWithIframe());
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a busca por Iframes';
    }

    protected function helpDescription(): string
    {
        return '<p>Aqui você busca direto no site de cada fabricante (ex: Cofap, Hipper Freios), sem sair do painel — escolha os fabricantes no topo e a página de busca deles carrega logo abaixo.</p>'.
            '<p>Como o conteúdo vem de fora, não dá pra ler o resultado automaticamente: depois de achar a peça no site do fabricante, digite o código manualmente pra adicionar à cotação.</p>';
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function manufacturersWithIframe(): Collection
    {
        $eligible = Manufacturer::query()
            ->where('is_active', true)
            ->whereNotNull('iframe_url')
            ->orderBy('name')
            ->get();

        return $this->filterToEnabledManufacturers($eligible);
    }

    /**
     * O conteúdo do iframe é opaco pra gente (cada fabricante formata sua própria página
     * de busca) — sem como ler um resultado específico dali, então o usuário digita o
     * código manualmente pra adicionar à cotação.
     */
    public function addToQuotation(int $manufacturer_id, string $codigo): void
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            Notification::make()
                ->title('Informe um código para adicionar.')
                ->warning()
                ->send();

            return;
        }

        $this->addExternalItemToQuotation(Source::Iframe, $manufacturer_id, $codigo);
    }
}
