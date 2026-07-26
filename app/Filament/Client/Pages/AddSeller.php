<?php

namespace App\Filament\Client\Pages;

use App\Enums\Role;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Substitui o antigo fluxo de convite por link — como um cliente pode ter mais de um
 * vendedor (ver User::sellers()), essa página é onde ele vê quem já está vinculado,
 * desanexa um vendedor (menos o último — ver removeSeller()) e se vincula a um vendedor
 * adicional informando o código dele, sem precisar criar outra conta.
 */
class AddSeller extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.client.pages.add-seller';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Meus vendedores';

    protected static ?string $title = 'Meus vendedores';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * A quem os vendedores exibidos/vinculados/desanexados pertencem — sempre o próprio
     * usuário logado. Existe como hook só pra App\Filament\Pages\Cliente\AddSeller poder
     * reaproveitar esta página inteira sob outro grupo de navegação, pra quem já foi
     * Role::Client e virou Role::Seller (ver User::hasClientHistory()) — o alvo continua
     * a mesma conta.
     */
    protected function targetUser(): User
    {
        return Auth::user();
    }

    /**
     * @return Collection<int, User>
     */
    public function sellers(): Collection
    {
        return $this->targetUser()->sellers()->orderBy('name')->get();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('referral_code')
                    ->label('Código do vendedor')
                    ->placeholder('Ex: AB12CD34')
                    ->helperText('Peça esse código pro vendedor — ele encontra em "Meu perfil" no painel dele.')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function addSeller(): void
    {
        $state = $this->form->getState();

        $seller = User::query()
            ->where('referral_code', $state['referral_code'])
            ->where('role', Role::Seller)
            ->first();

        if (! $seller) {
            Notification::make()
                ->title('Código de vendedor inválido.')
                ->danger()
                ->send();

            return;
        }

        if ($this->targetUser()->isLinkedToSeller($seller)) {
            Notification::make()
                ->title('Você já está vinculado a esse vendedor.')
                ->warning()
                ->send();

            return;
        }

        $this->targetUser()->linkToSeller($seller);

        $this->form->fill();

        Notification::make()
            ->title('Vendedor adicionado!')
            ->body('Já dá pra criar pedidos pra ele.')
            ->success()
            ->send();
    }

    /**
     * Nunca deixa desanexar o último vendedor — sem nenhum, a conta perde a capacidade
     * de criar pedidos (ver App\Filament\Client\Pages\CreateOrder), sem nenhuma forma
     * óbvia de voltar a vincular um (o campo de vendedor fica escondido com 0 opções).
     */
    public function removeSeller(int $seller_id): void
    {
        $seller = $this->sellers()->firstWhere('id', $seller_id);

        if (! $seller) {
            return;
        }

        if ($this->sellers()->count() <= 1) {
            Notification::make()
                ->title('Você precisa ter pelo menos um vendedor vinculado.')
                ->warning()
                ->send();

            return;
        }

        $this->targetUser()->sellers()->detach($seller->id);

        Notification::make()
            ->title('Vendedor desanexado.')
            ->success()
            ->send();
    }
}
