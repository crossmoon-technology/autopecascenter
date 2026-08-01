<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->helpAction(),
        ];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a página de Usuários';
    }

    protected function helpDescription(): string
    {
        return '<p>Lista todas as contas da plataforma — super admins, vendedores e clientes — num só lugar.</p>'.
            '<p>Use "Novo" para criar uma conta manualmente, escolhendo o papel (e o plano, se for vendedor). Para aprovar pagamento ou editar dados de um vendedor já existente, use a página de Vendedores.</p>';
    }
}
