<?php

namespace App\Filament\Pages\Buscas;

use App\Models\Quotation;
use App\Models\Quotation\Enums\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Quotations extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.buscas.quotations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Cotações';

    protected static ?string $title = 'Cotações';

    /**
     * Só mostra cotações já salvas — a cotação em aberto (carrinho) vive no widget do
     * topbar, não aqui; ela só aparece nessa listagem depois de salva (ver
     * App\Livewire\QuotationCart). Reabrir uma cotação daqui tira ela da lista de novo,
     * já que volta a ser "a aberta".
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Quotation::query()->where('user_id', Auth::id())->where('status', Status::Closed)->withCount('items'))
            ->recordUrl(fn (Quotation $record): string => ViewQuotation::getUrl(['quotation' => $record->id]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Cotação')
                    ->getStateUsing(fn (Quotation $record): string => $record->displayName())
                    ->searchable(['name'])
                    ->weight('semibold')
                    ->icon(Heroicon::OutlinedShoppingCart),
                TextColumn::make('items_count')
                    ->label('Peças'),
                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('reopen')
                    ->label('Reabrir')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('gray')
                    ->action(function (Quotation $record): void {
                        $record->reopen();

                        $this->dispatch('quotation-updated');

                        Notification::make()
                            ->title('Cotação reaberta — volte pro carrinho no topo da página pra continuar adicionando peças.')
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->label('Excluir')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Quotation $record): void {
                        $record->delete();

                        Notification::make()
                            ->title('Cotação excluída.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma cotação salva ainda')
            ->emptyStateDescription('Adicione peças ao carrinho no topo da página e clique em "Salvar cotação" quando terminar.')
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart);
    }
}
