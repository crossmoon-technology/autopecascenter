<?php

namespace App\Filament\Pages\Buscas;

use App\Enums\Role;
use App\Filament\Concerns\HasHelpAction;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class Clients extends Page implements HasTable
{
    use HasHelpAction;
    use InteractsWithTable;

    protected string $view = 'filament.pages.buscas.clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Vendas';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $title = 'Clientes';

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a página de Clientes';
    }

    protected function helpDescription(): string
    {
        return '<p>Todo cliente que se cadastra informando o seu código de vendedor (veja "Meu perfil") aparece aqui automaticamente.</p>'.
            '<p>Se preferir, use "Cadastrar cliente" pra criar a conta dele direto, sem precisar que ele mesmo se cadastre — útil só pra controle, já que todo pedido (inclusive os que você cria na mão em Pedidos) precisa estar associado a um cliente.</p>';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Auth::user()->clients()->getQuery())
            ->recordUrl(fn (User $record): string => ViewClient::getUrl(['client' => $record->id]))
            ->defaultSort('client_sellers.created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable()
                    ->weight('semibold')
                    ->icon(Heroicon::OutlinedUser),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('pending_orders')
                    ->label('Pedidos pendentes')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (User $record): int => $this->pendingOrdersCountFor($record))
                    ->color(fn (User $record): string => $this->pendingOrdersCountFor($record) > 0 ? 'warning' : 'gray'),
                TextColumn::make('pivot.created_at')
                    ->label('Vinculado em')
                    ->alignCenter()
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('createDirect')
                    ->label('Cadastrar cliente')
                    ->icon(Heroicon::OutlinedUserPlus)
                    // Cadastro direto, sem o cliente precisar informar o código dele
                    // mesmo — útil quando ele prefere ligar ou mandar mensagem em vez de
                    // se cadastrar sozinho.
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email'),
                        TextInput::make('document')
                            ->label('CPF')
                            ->required()
                            ->minLength(11)
                            ->maxLength(11)
                            // Único só entre outras contas Role::Client — quem já é
                            // vendedor em outro lugar pode virar cliente aqui também
                            // (ver User::linkToSeller()).
                            ->unique('users', 'document', modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('role', Role::Client->value)),
                    ])
                    ->action(function (array $data): void {
                        $client = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'document' => $data['document'],
                            'password' => Str::password(16),
                            'role' => Role::Client,
                        ]);

                        // Diferente do autocadastro, aqui é o próprio vendedor (já
                        // autenticado) quem garante o e-mail, não um desconhecido
                        // digitando por conta própria — não faz sentido exigir uma
                        // confirmação que ninguém vai receber (a senha é aleatória e
                        // nunca é entregue ao cliente; ele só entra depois de redefinir
                        // a senha em "Esqueci minha senha").
                        $client->markEmailAsVerified();

                        $client->linkToSeller(Auth::user());

                        Notification::make()
                            ->title('Cliente cadastrado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum cliente vinculado ainda')
            ->emptyStateDescription('Compartilhe seu código de vendedor (veja "Meu perfil") pra que clientes se cadastrem e comecem a enviar pedidos.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }

    private function pendingOrdersCountFor(User $client): int
    {
        return Order::query()
            ->forSeller(Auth::user())
            ->where('user_id', $client->id)
            ->whereNotIn('status', [Status::Finished, Status::Cancelled])
            ->count();
    }
}
