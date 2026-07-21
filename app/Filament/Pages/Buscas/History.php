<?php

namespace App\Filament\Pages\Buscas;

use App\Models\SearchHistory;
use App\Models\SearchHistory\Enums\Method;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class History extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.buscas.history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Histórico';

    protected static ?string $title = 'Histórico';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SearchHistory::query()->where('user_id', Auth::id()))
            ->recordUrl(fn (SearchHistory $record): string => match ($record->method) {
                Method::Api => Api::getUrl(['codigo' => $record->query]),
                Method::Database => CatalogDatabaseSearch::getUrl(['codigo' => $record->query]),
            })
            ->columns([
                TextColumn::make('query')
                    ->label('Texto buscado')
                    ->searchable(),
                TextColumn::make('method')
                    ->label('Método')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhuma busca registrada ainda')
            ->emptyStateDescription('Suas buscas em Base de dados e API vão aparecer aqui.')
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }
}
