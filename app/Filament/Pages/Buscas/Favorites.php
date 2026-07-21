<?php

namespace App\Filament\Pages\Buscas;

use App\Models\FavoriteList;
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
use UnitEnum;

class Favorites extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.buscas.favorites';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Favoritas';

    protected static ?string $title = 'Favoritas';

    public function mount(): void
    {
        // Garante que "Lista padrão" já apareça mesmo pra quem nunca favoritou nada.
        Auth::user()->defaultFavoriteList();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => FavoriteList::query()->where('user_id', Auth::id())->withCount('parts'))
            ->recordUrl(fn (FavoriteList $record): string => ViewFavoriteList::getUrl(['list' => $record->id]))
            ->defaultSort('is_default', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Lista')
                    ->searchable()
                    ->weight('semibold')
                    ->icon(Heroicon::OutlinedFolder),
                TextColumn::make('is_default')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): ?string => $state ? 'Padrão' : null)
                    ->color('warning'),
                TextColumn::make('parts_count')
                    ->label('Peças'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nova lista')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome da lista')
                            ->required()
                            ->maxLength(255)
                            ->unique('favorite_lists', 'name', modifyRuleUsing: fn ($rule) => $rule->where('user_id', Auth::id())),
                    ])
                    ->action(function (array $data): void {
                        Auth::user()->favoriteLists()->create(['name' => $data['name']]);

                        Notification::make()
                            ->title('Lista criada.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('rename')
                    ->label('Renomear')
                    ->icon(Heroicon::OutlinedPencil)
                    ->schema(fn (FavoriteList $record) => [
                        TextInput::make('name')
                            ->label('Nome da lista')
                            ->required()
                            ->maxLength(255)
                            ->default($record->name)
                            ->unique('favorite_lists', 'name', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('user_id', Auth::id())),
                    ])
                    ->fillForm(fn (FavoriteList $record): array => ['name' => $record->name])
                    ->action(function (FavoriteList $record, array $data): void {
                        $record->update(['name' => $data['name']]);
                    }),
                Action::make('delete')
                    ->label('Excluir')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FavoriteList $record): bool => $record->isDeletable())
                    ->action(function (FavoriteList $record): void {
                        $record->delete();

                        Notification::make()
                            ->title('Lista excluída.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma lista ainda')
            ->emptyStateIcon(Heroicon::OutlinedFolder);
    }
}
