<?php

namespace App\Filament\Resources\Informativos\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Informativos\InformativoResource;
use Filament\Resources\Pages\ListRecords;

class ListInformativos extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = InformativoResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a página de Informativos';
    }

    protected function helpDescription(): string
    {
        return '<p>Comunicados e avisos publicados pela Auto Peças Center aparecem aqui.</p>'.
            '<p>Só o SuperAdmin pode criar ou editar — pra você, é uma tela de consulta.</p>';
    }
}
