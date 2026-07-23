<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasHelpAction;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Só existe pra poder pendurar o botão de ajuda (ver App\Filament\Concerns\HasHelpAction)
 * — o Dashboard padrão do Filament não tem onde encaixar isso sem estender a classe.
 * Registrado só nos painéis do vendedor (Admin/SuperAdmin, ver *PanelProvider) — o painel
 * do Cliente continua usando o Dashboard padrão do Filament.
 */
class Dashboard extends BaseDashboard
{
    use HasHelpAction;

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona o Painel de Controle';
    }

    protected function helpDescription(): string
    {
        return '<p>Aqui você acompanha o total de pedidos dos seus clientes por status — pendente, processando, finalizado e cancelado.</p>'.
            '<p>Use o seletor no canto do gráfico pra trocar o período (hoje, últimos 7 dias, últimos 31 dias ou todos).</p>';
    }
}
