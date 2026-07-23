<?php

namespace App\Filament\Pages\Buscas\Concerns;

use App\Models\Part;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItem\Enums\Source;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait AddsToQuotation
{
    /**
     * Usado por lugares sem ícone pra "marcar" o estado (ex: a action de texto em
     * Favoritos) — idempotente, clicar de novo não duplica nem remove.
     */
    protected function addPartToQuotation(Part $part, int $quantity = 1): void
    {
        $quotation = Auth::user()->openQuotation();
        $quotation->addPart($part, $quantity);

        $this->notifyAddedToQuotation($quotation);
    }

    protected function addExternalItemToQuotation(Source $source, int $manufacturer_id, string $codigo, ?string $descricao = null, int $quantity = 1): void
    {
        $quotation = Auth::user()->openQuotation();
        $quotation->addExternalItem($source, $manufacturer_id, $codigo, $descricao, $quantity);

        $this->notifyAddedToQuotation($quotation);
    }

    /**
     * Usado pelos botões de carrinho nos cards de resultado (Base de dados, ViewPart) —
     * mesmo espírito do toggle de favoritar: clicar de novo remove, e o botão fica
     * marcado (verde) enquanto a peça estiver na cotação em aberto.
     *
     * @return bool true se a peça passou a estar na cotação, false se foi removida.
     */
    protected function togglePartInQuotation(Part $part): bool
    {
        $existingItem = Auth::user()->openQuotationOrNull()?->items()->where('part_id', $part->id)->first();

        if ($existingItem) {
            $existingItem->delete();
            $this->notifyRemovedFromQuotation();

            return false;
        }

        $this->addPartToQuotation($part);

        return true;
    }

    /**
     * Mesma ideia de togglePartInQuotation(), mas pra itens sem Part (API) — a
     * identidade do item é o par fabricante+código dentro daquela origem.
     *
     * @return bool true se o item passou a estar na cotação, false se foi removido.
     */
    protected function toggleExternalItemInQuotation(Source $source, int $manufacturer_id, string $codigo, ?string $descricao = null): bool
    {
        $existingItem = Auth::user()->openQuotationOrNull()?->items()
            ->where(['source' => $source, 'manufacturer_id' => $manufacturer_id, 'codigo' => $codigo])
            ->first();

        if ($existingItem) {
            $existingItem->delete();
            $this->notifyRemovedFromQuotation();

            return false;
        }

        $this->addExternalItemToQuotation($source, $manufacturer_id, $codigo, $descricao);

        return true;
    }

    /**
     * @return array<int> ids das Parts já presentes na cotação em aberto — usado pra
     *                    marcar (verde) o botão de carrinho nos cards já adicionados.
     */
    protected function currentlyQuotedPartIds(): array
    {
        return Auth::user()->openQuotationOrNull()?->items()->whereNotNull('part_id')->pluck('part_id')->all() ?? [];
    }

    /**
     * @return array<string> chaves "manufacturer_id|codigo" dos itens externos (sem
     *                       Part) de uma origem já presentes na cotação em aberto.
     */
    protected function currentlyQuotedExternalKeys(Source $source): array
    {
        return Auth::user()->openQuotationOrNull()?->items()
            ->where('source', $source)
            ->get()
            ->map(fn (QuotationItem $item): string => "{$item->manufacturer_id}|{$item->codigo}")
            ->all() ?? [];
    }

    /**
     * O widget do carrinho no topbar (App\Livewire\QuotationCart) é um componente Livewire
     * irmão, sempre presente na página — esse evento é como ele sabe que precisa
     * re-renderizar com a contagem/itens atualizados sem precisar de um F5.
     */
    private function notifyAddedToQuotation(Quotation $quotation): void
    {
        $this->dispatch('quotation-updated');

        Notification::make()
            ->title("Adicionada à \"{$quotation->displayName()}\".")
            ->success()
            ->send();
    }

    private function notifyRemovedFromQuotation(): void
    {
        $this->dispatch('quotation-updated');

        Notification::make()
            ->title('Removida da cotação.')
            ->success()
            ->send();
    }
}
