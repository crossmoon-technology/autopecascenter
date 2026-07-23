<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Pages\Buscas\Concerns\ResolvesPreferredManufacturers;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use App\Models\Quotation;
use App\Models\Quotation\Enums\Status as QuotationStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Orders extends Page implements HasTable
{
    use HasHelpAction;
    use InteractsWithTable;
    use ResolvesPreferredManufacturers;

    protected string $view = 'filament.pages.buscas.orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Vendas';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $title = 'Pedidos';

    /**
     * Visão única e simples de todos os pedidos de todos os clientes convidados por
     * este vendedor — sem precisar entrar em cada cliente pra ver o que chegou. O
     * status é alternado direto na linha (pending por padrão, ver App\Models\Order\Enums\Status)
     * — exceto Finalizado, que exige uma cotação associada primeiro (botão "Anexar cotação").
     */
    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a página de Pedidos';
    }

    protected function helpDescription(): string
    {
        return '<p>Todos os pedidos enviados pelos seus clientes aparecem aqui, com o status de cada um — você pode atualizar direto na lista.</p>'.
            '<p>Se um cliente preferir ligar ou mandar mensagem em vez de usar o painel, use "Novo pedido" pra registrar o pedido dele na mão.</p>';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Order::query()
                    ->whereHas('user', fn (Builder $query) => $query->where('invited_by_id', Auth::id()))
                    ->with(['user', 'items.preferredManufacturers', 'quotation'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->weight('semibold')
                    ->icon(Heroicon::OutlinedUser),
                TextColumn::make('notes')
                    ->label('Comentário')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('quotation.name')
                    ->label('Cotação')
                    ->getStateUsing(fn (Order $record): ?string => $record->quotation?->displayName())
                    ->placeholder('Nenhuma')
                    ->url(fn (Order $record): ?string => $record->quotation_id
                        ? ViewQuotation::getUrl(['quotation' => $record->quotation_id])
                        : null),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        Status::Pending->value => Status::Pending->getLabel(),
                        Status::Processing->value => Status::Processing->getLabel(),
                        Status::Finished->value => Status::Finished->getLabel(),
                        Status::Cancelled->value => Status::Cancelled->getLabel(),
                    ])
                    ->selectablePlaceholder(false)
                    ->alignCenter()
                    ->extraAttributes(['style' => 'width: 11rem; min-width: 0; margin-inline: auto;'])
                    ->extraInputAttributes(['style' => 'font-size: 0.8125rem; padding-top: 0.375rem; padding-bottom: 0.375rem;'])
                    ->updateStateUsing(function (Order $record, mixed $state): string {
                        if ($state === Status::Finished->value && ! $record->quotation_id) {
                            Notification::make()
                                ->title('Associe uma cotação antes de marcar como finalizado.')
                                ->warning()
                                ->send();

                            return $record->status->value;
                        }

                        $record->update(['status' => $state]);

                        $this->dispatch('order-status-updated');

                        return $state;
                    }),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->alignCenter()
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Status::Pending->value => Status::Pending->getLabel(),
                        Status::Processing->value => Status::Processing->getLabel(),
                        Status::Finished->value => Status::Finished->getLabel(),
                        Status::Cancelled->value => Status::Cancelled->getLabel(),
                    ]),
            ])
            ->headerActions([
                Action::make('createOrder')
                    ->label('Novo pedido')
                    ->icon(Heroicon::OutlinedPlus)
                    // O id é o alvo do App\Livewire\OnboardingTutorial (ver seu passo final).
                    ->extraAttributes(['id' => 'onboarding-target-create-order'])
                    // Mesmo formato que o cliente usa em App\Filament\Client\Pages\CreateOrder
                    // (peças com código/quantidade/fabricantes de preferência + comentário) —
                    // assim um pedido criado pelo vendedor (ex: por telefone) vira exatamente
                    // o mesmo tipo de registro que um enviado pelo próprio cliente, sem um
                    // formato alternativo pra tratar em GuidedQuotation e no histórico do cliente.
                    ->schema([
                        Select::make('user_id')
                            ->label('Cliente')
                            ->options(fn (): array => $this->clientOptionsForNewOrder())
                            ->searchable()
                            ->required(),
                        Repeater::make('items')
                            ->label('Peças')
                            ->schema([
                                TextInput::make('description')
                                    ->label('Código da peça')
                                    ->placeholder('Ex: HG 41297')
                                    ->required()
                                    ->maxLength(500)
                                    ->columnSpan(2),
                                TextInput::make('quantity')
                                    ->label('Quantidade')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                Select::make('manufacturer_ids')
                                    ->label('Fabricantes de preferência (opcional)')
                                    ->helperText('A ordem escolhida será considerada como ordem de preferência.')
                                    ->multiple()
                                    ->options(fn (): array => $this->manufacturerOptionsForNewOrder())
                                    ->searchable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->addActionLabel('Adicionar peça')
                            ->minItems(1)
                            ->required()
                            ->reorderable(false),
                        Textarea::make('notes')
                            ->label('Comentário adicional (opcional)')
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->fillForm(fn (): array => ['items' => []])
                    ->action(function (array $data): void {
                        // Não confia no valor de user_id vindo do form só porque as opções do
                        // Select já são restritas aos clientes convidados — reconfirma a posse
                        // no servidor antes de criar qualquer coisa em nome de outra conta.
                        $client = Auth::user()->invitedClients()->findOrFail($data['user_id']);

                        $order = $client->orders()->create([
                            'notes' => $data['notes'] ?: null,
                        ]);

                        foreach ($data['items'] as $itemData) {
                            $item = $order->items()->create([
                                'description' => $itemData['description'],
                                'quantity' => $itemData['quantity'],
                            ]);

                            if (! empty($itemData['manufacturer_ids'])) {
                                $syncData = collect($itemData['manufacturer_ids'])
                                    ->values()
                                    ->mapWithKeys(fn (int $manufacturer_id, int $position): array => [$manufacturer_id => ['position' => $position]])
                                    ->all();

                                $item->preferredManufacturers()->sync($syncData);
                            }
                        }

                        $this->dispatch('order-status-updated');

                        Notification::make()
                            ->title('Pedido criado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('startGuidedQuotation')
                    ->label('Iniciar cotação guiada')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('primary')
                    ->visible(fn (Order $record): bool => in_array($record->status, [Status::Pending, Status::Processing], true))
                    ->url(fn (Order $record): string => GuidedQuotation::getUrl(['order' => $record->id])),
                Action::make('attachQuotation')
                    ->label(fn (Order $record): string => $record->quotation_id ? 'Trocar cotação' : 'Anexar cotação')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->color('gray')
                    ->schema([
                        Select::make('quotation_id')
                            ->label('Cotação')
                            ->options(fn (): array => $this->quotationOptions())
                            ->placeholder('Nenhuma'),
                    ])
                    ->fillForm(fn (Order $record): array => ['quotation_id' => $record->quotation_id])
                    ->action(function (Order $record, array $data): void {
                        $quotation_id = $data['quotation_id'] ?? null;

                        $record->update([
                            'quotation_id' => $quotation_id,
                            // Sem cotação, um pedido não pode continuar marcado como finalizado.
                            'status' => (! $quotation_id && $record->status === Status::Finished)
                                ? Status::Pending
                                : $record->status,
                        ]);

                        $this->dispatch('order-status-updated');

                        Notification::make()
                            ->title($quotation_id ? 'Cotação associada.' : 'Cotação removida.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum pedido recebido ainda')
            ->emptyStateDescription('Os pedidos enviados pelos seus clientes convidados aparecem aqui.')
            ->emptyStateIcon(Heroicon::OutlinedInboxStack);
    }

    /**
     * @return array<int, string>
     */
    private function quotationOptions(): array
    {
        return Quotation::query()
            ->where('user_id', Auth::id())
            ->where('status', QuotationStatus::Closed)
            ->orderByDesc('updated_at')
            ->get()
            ->mapWithKeys(fn (Quotation $quotation): array => [$quotation->id => $quotation->displayName()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function manufacturerOptionsForNewOrder(): array
    {
        $eligible = Manufacturer::query()->where('is_active', true)->orderBy('name')->get();

        return $this->filterToEnabledManufacturers($eligible)->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private function clientOptionsForNewOrder(): array
    {
        return Auth::user()->invitedClients()->orderBy('name')->pluck('name', 'id')->all();
    }
}
