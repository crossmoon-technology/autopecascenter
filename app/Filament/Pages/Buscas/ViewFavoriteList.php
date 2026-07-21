<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Pages\Buscas\Concerns\AddsToQuotation;
use App\Models\FavoriteList;
use App\Models\Part;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ViewFavoriteList extends Page implements HasTable
{
    use AddsToQuotation;
    use InteractsWithTable;

    protected static ?string $slug = 'favoritas/{list}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.buscas.view-favorite-list';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    public FavoriteList $favoriteList;

    public function mount(int|string $list): void
    {
        $this->favoriteList = auth()->user()->favoriteLists()->findOrFail($list);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->favoriteList->name;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->favoriteList->parts()->getQuery())
            ->recordUrl(fn (Part $record): string => ViewPart::getUrl(['record' => $record->id]))
            ->columns([
                ImageColumn::make('catalog.manufacturer.logo')
                    ->disk('public')
                    ->label('Fabricante')
                    ->imageHeight(20)
                    ->alignCenter(),
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('catalog.name')
                    ->label('Catálogo')
                    ->searchable(),
                TextColumn::make('pivot.note')
                    ->label('Nota')
                    ->placeholder('—')
                    ->limit(40)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('editNote')
                    ->label('Nota')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->fillForm(fn (Part $record): array => [
                        'note' => $this->favoriteList->parts()->whereKey($record->id)->first()?->pivot?->note,
                    ])
                    ->schema([
                        Textarea::make('note')
                            ->label('Nota')
                            ->placeholder('Ex: cliente pede sempre essa, combinar preço...')
                            ->rows(3),
                    ])
                    ->action(function (Part $record, array $data): void {
                        $this->favoriteList->parts()->updateExistingPivot($record->id, [
                            'note' => $data['note'],
                        ]);

                        Notification::make()
                            ->title('Nota salva.')
                            ->success()
                            ->send();
                    }),
                Action::make('addToQuotation')
                    ->label('Adicionar à cotação')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->color('gray')
                    ->action(fn (Part $record) => $this->addPartToQuotation($record)),
                Action::make('remove')
                    ->label('Remover da lista')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->action(function (Part $record): void {
                        $this->favoriteList->parts()->detach($record->id);

                        Notification::make()
                            ->title('Removida da lista.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma peça nessa lista ainda')
            ->emptyStateDescription('Favorite uma peça e escolha essa lista pra vê-la aqui.')
            ->emptyStateIcon(Heroicon::OutlinedStar);
    }
}
