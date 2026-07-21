<?php

namespace App\Livewire;

use App\Models\Quotation;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Widget de carrinho no topbar do painel — mostra a cotação em aberto do usuário (o
 * "carrinho"), como num e-commerce: peças vão sendo adicionadas de qualquer página
 * (Base de dados, API, Iframes, Favoritos) e só viram uma cotação de verdade, visível na
 * listagem de Cotações, quando o usuário clica em "Salvar" aqui.
 *
 * "Salvar" é um campo de nome + botão simples dentro do próprio drawer, não uma Action
 * do Filament com seu próprio modal — nesting o modal de uma Action dentro do slide-over
 * manual (<x-filament::modal>) quebra o empilhamento de cliques (o slide-over não faz
 * parte da pilha de modais do sistema de Actions), então ficaria clicável só visualmente.
 * Resolver isso inline evita esse conflito de vez.
 */
class QuotationCart extends Component
{
    public string $name = '';

    /**
     * Disparado por App\Filament\Pages\Buscas\Concerns\AddsToQuotation sempre que uma
     * peça é adicionada em outra página — como esse widget vive fora do componente que
     * disparou o evento, precisa escutar pra saber que precisa re-renderizar.
     */
    #[On('quotation-updated')]
    public function refresh(): void {}

    public function currentQuotation(): ?Quotation
    {
        if (! Auth::check()) {
            return null;
        }

        return Auth::user()->openQuotationOrNull()?->load('items.manufacturer');
    }

    public function removeItem(int $item_id): void
    {
        $this->currentQuotation()?->items()->whereKey($item_id)->delete();

        $this->dispatch('quotation-updated');
    }

    public function save(): void
    {
        $quotation = $this->currentQuotation();

        if (! $quotation || $quotation->items()->doesntExist()) {
            Notification::make()
                ->title('Adicione ao menos uma peça antes de salvar.')
                ->warning()
                ->send();

            return;
        }

        $quotation->close($this->name);
        $this->name = '';

        $this->dispatch('quotation-updated');
        $this->dispatch('close-modal', id: 'quotation-cart');

        Notification::make()
            ->title('Cotação salva.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.quotation-cart', [
            'quotation' => $this->currentQuotation(),
        ]);
    }
}
