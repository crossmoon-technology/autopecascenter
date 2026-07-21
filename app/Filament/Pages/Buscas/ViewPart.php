<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Pages\Buscas\Concerns\AddsToQuotation;
use App\Filament\Pages\Buscas\Concerns\ManagesFavoriteLists;
use App\Models\Part;
use App\Services\QrCode\QrCodeGenerator;
use BackedEnum;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ViewPart extends Page implements HasActions
{
    use AddsToQuotation;
    use InteractsWithActions;
    use ManagesFavoriteLists;

    protected static ?string $slug = 'pecas/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.buscas.view-part';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    public Part $part;

    public bool $isFavorited = false;

    public bool $isQuoted = false;

    public function mount(int|string $record): void
    {
        $this->part = Part::query()
            ->with('catalog.manufacturer')
            ->findOrFail($record);

        $this->isFavorited = Auth::user()->favoritedPartIds()->contains($this->part->id);
        $this->isQuoted = in_array($this->part->id, $this->currentlyQuotedPartIds(), true);
    }

    public function getTitle(): string|Htmlable
    {
        return "Peça {$this->part->codigo}";
    }

    /**
     * Toggle rápido: desfavoritar tira a peça de TODAS as listas do usuário; favoritar
     * manda pra "Lista padrão" (quem quiser uma lista específica usa a action ao lado,
     * ver pickFavoriteListAction em ManagesFavoriteLists).
     */
    public function toggleFavorite(): void
    {
        $user = Auth::user();

        if ($this->isFavorited) {
            $user->detachPartFromAllFavoriteLists($this->part->id);
        } else {
            $user->defaultFavoriteList()->parts()->syncWithoutDetaching([$this->part->id]);
        }

        $this->isFavorited = ! $this->isFavorited;
    }

    protected function markPartAsFavorited(int $part_id): void
    {
        $this->isFavorited = true;
    }

    public function addToQuotation(): void
    {
        $this->isQuoted = $this->togglePartInQuotation($this->part);
    }

    public function shareUrl(): string
    {
        return $this->part->publicShareUrl();
    }

    public function shareQrCodeSvg(): string
    {
        return app(QrCodeGenerator::class)->svg($this->shareUrl());
    }

    /**
     * Atributos e conversoes vêm de jsonl arbitrário por fabricante, então um valor
     * pode ser uma string, um array simples, ou (raramente) um array aninhado —
     * formata recursivamente em vez de assumir uma estrutura fixa.
     */
    public function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => $this->formatValue($item))->implode(', ');
        }

        return (string) $value;
    }
}
