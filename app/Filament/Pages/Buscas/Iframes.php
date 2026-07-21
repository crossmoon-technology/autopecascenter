<?php

namespace App\Filament\Pages\Buscas;

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
