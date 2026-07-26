<?php

namespace App\Filament\Resources\Sellers\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Sellers\SellerResource;
use Filament\Resources\Pages\ListRecords;

class ListSellers extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a página de Vendedores';
    }

    protected function helpDescription(): string
    {
        return '<p>Todo vendedor confirma o e-mail e escolhe um plano sozinho — a avaliação gratuita de 7 dias começa e o acesso é liberado na hora, automaticamente.</p>'.
            '<p>Como a cobrança não acontece pela plataforma, o pagamento de planos Básico/Profissional precisa ser aprovado manualmente aqui ("Aprovar pagamento") depois que um representante confirmar com o vendedor — sem isso, o acesso é encerrado quando a avaliação gratuita vencer.</p>';
    }
}
