<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Pages\Concerns\ScopesManufacturersToInvitingSeller;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CreateOrder extends Page implements HasForms
{
    use InteractsWithForms;
    use ScopesManufacturersToInvitingSeller;

    protected string $view = 'filament.client.pages.create-order';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Criar pedido';

    protected static ?string $title = 'Criar pedido';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillBlankForm();
    }

    /**
     * De quem são os pedidos criados e a quem os vendedores pertencem — sempre o próprio
     * usuário logado. Existe como hook (em vez de usar Auth::user() direto no resto da
     * classe) só pra App\Filament\Pages\Cliente\CreateOrder poder reaproveitar esta
     * página inteira sob outro grupo de navegação, pra quem já foi Role::Client e virou
     * Role::Seller (ver User::hasClientHistory()) — o alvo continua sendo a mesma conta.
     */
    protected function targetUser(): User
    {
        return Auth::user();
    }

    /**
     * @return Collection<int, User>
     */
    private function sellers(): Collection
    {
        return $this->targetUser()->sellers()->orderBy('name')->get();
    }

    /**
     * O vendedor escolhido no formulário — cai automaticamente pro único vendedor do
     * cliente quando ele só tem um (o campo nem aparece nesse caso, ver form()). Um id
     * que não pertence aos vendedores do cliente (formulário adulterado) é ignorado, sem
     * derrubar a página — só faz a lista de fabricantes cair no fallback "todos os ativos".
     */
    private function selectedSeller(): ?User
    {
        $sellers = $this->sellers();
        $seller_id = $this->data['seller_id'] ?? null;

        if ($seller_id === null) {
            return $sellers->count() === 1 ? $sellers->first() : null;
        }

        return $sellers->firstWhere('id', $seller_id);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('seller_id')
                    ->label('Vendedor')
                    ->helperText('Pra qual vendedor esse pedido é.')
                    ->options(fn () => $this->sellers()->pluck('name', 'id'))
                    ->required()
                    // Um cliente com um único vendedor não precisa escolher nada — o
                    // campo já vem preenchido (ver fillBlankForm()) e só aparece de
                    // verdade quando existe uma escolha real a fazer. Precisa continuar
                    // dehydratado mesmo escondido, senão getState() descarta o valor e
                    // save() nunca acha o vendedor (ver Filament\Schemas\Components\Concerns\HasState::isDehydrated()).
                    ->visible(fn (): bool => $this->sellers()->count() > 1)
                    ->dehydratedWhenHidden()
                    ->live()
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->label('Peças')
                    ->schema([
                        // As 3 classes onboarding-target-* abaixo são os alvos do
                        // App\Livewire\OnboardingTutorial, que detalha campo por campo
                        // nesse primeiro item antes do usuário enviar o pedido.
                        TextInput::make('description')
                            ->label('Código da peça')
                            ->placeholder('Ex: HG 41297')
                            ->required()
                            ->maxLength(500)
                            ->extraAttributes(['class' => 'onboarding-target-description'])
                            ->columnSpan(2),
                        TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->extraAttributes(['class' => 'onboarding-target-quantity']),
                        Select::make('manufacturer_ids')
                            ->label('Fabricantes de preferência (opcional)')
                            ->helperText('A ordem escolhida será considerada como ordem de preferência.')
                            ->multiple()
                            ->options(fn () => $this->manufacturersScopedToInvitingSeller($this->selectedSeller())->pluck('name', 'id'))
                            ->searchable()
                            ->extraAttributes(['class' => 'onboarding-target-manufacturers'])
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->addActionLabel('Adicionar peça')
                    // O id é o alvo do App\Livewire\OnboardingTutorial (ver seu passo final).
                    ->addAction(fn (Action $action): Action => $action->extraAttributes(['id' => 'onboarding-target-add-item']))
                    ->minItems(1)
                    ->required()
                    ->reorderable(false),
                Textarea::make('notes')
                    ->label('Comentário adicional (opcional)')
                    ->placeholder('Alguma observação sobre esse pedido?')
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $seller = $this->sellers()->firstWhere('id', $state['seller_id'] ?? null);

        if (! $seller) {
            Notification::make()
                ->title('Escolha um vendedor antes de enviar o pedido.')
                ->danger()
                ->send();

            return;
        }

        $order = $this->targetUser()->orders()->create([
            'seller_id' => $seller->id,
            'notes' => $state['notes'] ?: null,
        ]);

        foreach ($state['items'] as $itemData) {
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

        $this->fillBlankForm();

        // Genérico de propósito (não amarrado a nenhum estado de tutorial) — só um hook
        // de "um pedido acabou de ser criado" pro App\Livewire\OnboardingTutorial ouvir
        // do lado do JS (ver resources/js/onboarding-tour.js) enquanto guia o cliente
        // pelo fluxo de criar/acompanhar/cancelar um pedido de teste.
        $this->dispatch('order-created', orderId: $order->id);

        Notification::make()
            ->title('Pedido enviado!')
            ->success()
            ->send();
    }

    private function fillBlankForm(): void
    {
        $sellers = $this->sellers();

        $this->form->fill([
            'seller_id' => $sellers->count() === 1 ? $sellers->first()->id : null,
            'items' => [
                ['description' => '', 'quantity' => 1, 'manufacturer_ids' => []],
            ],
            'notes' => null,
        ]);
    }
}
