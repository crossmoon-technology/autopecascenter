<?php

namespace App\Filament\Pages\Perfil;

use App\Enums\Role;
use App\Filament\Concerns\HasHelpAction;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Só leitura de propósito ("página de visualização do cadastro") — os únicos dados
 * editáveis que o usuário tem hoje (a logo) já têm seu próprio lugar em
 * App\Filament\Pages\Configuracoes\GeneralSettings, então essa página não duplica isso.
 * Uma única classe compartilhada pelos 3 painéis (ver *PanelProvider), já que o conteúdo
 * é o mesmo em qualquer papel — só mostra os dados do próprio usuário logado.
 */
class ViewProfile extends Page
{
    use HasHelpAction;

    protected string $view = 'filament.pages.perfil.view-profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Meu perfil';

    protected static ?string $title = 'Meu perfil';

    public function getUser(): User
    {
        return Auth::user();
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona o Meu perfil';
    }

    protected function helpDescription(): string
    {
        return '<p>Essa página é só pra consulta — mostra seus dados de cadastro, sua função e quando você aceitou os termos de privacidade.</p>'.
            '<p>Pra trocar sua logo, vá em Configurações &gt; Geral.</p>';
    }

    public function isSeller(): bool
    {
        return $this->getUser()->role !== Role::Client;
    }

    public function formattedDocument(): ?string
    {
        $document = $this->getUser()->document;

        if (! $document || strlen($document) !== 11) {
            return $document;
        }

        return substr($document, 0, 3).'.'.substr($document, 3, 3).'.'.substr($document, 6, 3).'-'.substr($document, 9, 2);
    }
}
