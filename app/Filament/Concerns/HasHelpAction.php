<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Botão "?" no cabeçalho de uma página específica do painel do vendedor, com um modal
 * explicando como aquela funcionalidade funciona — diferente do botão "?" do topbar (ver
 * App\Livewire\HelpMenu), que é genérico (tutorial/FAQ), esse aqui é sobre a página atual.
 * Cada classe que usa esse trait só precisa implementar helpTitle()/helpDescription() e
 * incluir $this->helpAction() no retorno de getHeaderActions().
 */
trait HasHelpAction
{
    protected function helpAction(): Action
    {
        return Action::make('help')
            ->label('')
            ->tooltip('Como funciona essa página?')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('gray')
            ->modalHeading($this->helpTitle())
            ->modalDescription(new HtmlString($this->helpDescription()))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar');
    }

    abstract protected function helpTitle(): string;

    abstract protected function helpDescription(): string;
}
