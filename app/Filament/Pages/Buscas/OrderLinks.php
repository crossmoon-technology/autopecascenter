<?php

namespace App\Filament\Pages\Buscas;

use App\Enums\Role;
use App\Filament\Concerns\HasHelpAction;
use App\Models\OrderLink;
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
use UnitEnum;

class OrderLinks extends Page implements HasTable
{
    use HasHelpAction;
    use InteractsWithTable;

    protected string $view = 'filament.pages.buscas.order-links';

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
        return '<p>Aqui você convida clientes novos e acompanha os que já estão cadastrados.</p>'.
            '<p>Clique em "Convidar cliente" pra criar um registro — o link de cadastro só é gerado depois, na página do cliente, quando você clicar em "Gerar link". Envie esse link pra ele criar a própria conta.</p>'.
            '<p>Se preferir, use "Cadastrar cliente" pra criar a conta dele direto, sem link nenhum — útil só pra controle, já que todo pedido (inclusive os que você cria na mão em Pedidos) precisa estar associado a um cliente.</p>';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OrderLink::query()->where('user_id', Auth::id())->with('registeredUser.orders'))
            ->recordUrl(fn (OrderLink $record): string => ViewOrderLink::getUrl(['orderLink' => $record->id]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('label')
                    ->label('Rótulo')
                    ->getStateUsing(fn (OrderLink $record): string => $record->displayLabel())
                    ->searchable(['label'])
                    ->weight('semibold')
                    ->icon(Heroicon::OutlinedLink),
                TextColumn::make('registeredUser.name')
                    ->label('Cliente')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (OrderLink $record): string => $record->statusLabel())
                    ->color(fn (OrderLink $record): string => $record->statusColor()),
                TextColumn::make('pending_orders')
                    ->label('Pedidos pendentes')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (OrderLink $record): int => $record->pendingOrdersCount())
                    ->color(fn (OrderLink $record): string => $record->pendingOrdersCount() > 0 ? 'warning' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->alignCenter()
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Convidar cliente')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        TextInput::make('label')
                            ->label('Rótulo')
                            ->placeholder('Ex: Oficina do João')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        // Sem token aqui de propósito — só é gerado quando o vendedor
                        // clica em "Gerar link" na página do cliente (ver
                        // App\Filament\Pages\Buscas\ViewOrderLink::generateLink()), não
                        // a cada registro criado.
                        Auth::user()->orderLinks()->create([
                            'label' => $data['label'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Cliente criado.')
                            ->body('Abra o cliente e clique em "Gerar link" quando quiser enviar o convite.')
                            ->success()
                            ->send();
                    }),
                Action::make('createDirect')
                    ->label('Cadastrar cliente')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('gray')
                    // Cadastro direto, sem link nenhum — o vendedor nunca deve usar o
                    // link do próprio cliente pra se cadastrar em nome dele; esse é o
                    // fluxo certo pra isso (ver helpDescription() acima).
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
                            ->unique('users', 'document'),
                    ])
                    ->action(function (array $data): void {
                        $client = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'document' => $data['document'],
                            'password' => Str::password(16),
                            'role' => Role::Client,
                            'invited_by_id' => Auth::id(),
                        ]);

                        Auth::user()->orderLinks()->create([
                            'label' => $data['name'],
                            'used_at' => now(),
                            'registered_user_id' => $client->id,
                        ]);

                        Notification::make()
                            ->title('Cliente cadastrado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('rename')
                    ->label('Renomear')
                    ->icon(Heroicon::OutlinedPencil)
                    ->color('gray')
                    ->schema(fn (OrderLink $record): array => [
                        TextInput::make('label')
                            ->label('Rótulo')
                            ->placeholder('Ex: Oficina do João')
                            ->maxLength(255)
                            ->default($record->label),
                    ])
                    ->fillForm(fn (OrderLink $record): array => ['label' => $record->label])
                    ->action(function (OrderLink $record, array $data): void {
                        $record->update(['label' => $data['label'] ?? null]);
                    }),
                Action::make('delete')
                    ->label('Excluir')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (OrderLink $record): void {
                        $record->delete();

                        Notification::make()
                            ->title('Link excluído.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum cliente cadastrado ainda')
            ->emptyStateDescription('Convide um cliente e envie o link de cadastro pra ele começar a enviar pedidos.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}
