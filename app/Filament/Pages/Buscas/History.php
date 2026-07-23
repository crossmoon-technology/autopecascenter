<?php

namespace App\Filament\Pages\Buscas;

use App\Filament\Concerns\HasHelpAction;
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
    use HasHelpAction;
    use InteractsWithTable;

    protected string $view = 'filament.pages.buscas.history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Buscas';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Histórico';

    protected static ?string $title = 'Histórico';

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona o Histórico';
    }

    protected function helpDescription(): string
    {
        return '<p>Toda busca feita na Base de dados ou na API fica registrada aqui, com as suas 100 mais recentes.</p>'.
            '<p>Clique em qualquer linha pra repetir a busca com o mesmo termo.</p>';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                // ->limit() sozinho não dá porque o Filament acrescenta o próprio
                // limit/offset da paginação em cima da query — o de baixo sempre vence,
                // então o limite de 100 nunca chegaria a valer. Restringindo pelos IDs
                // dos 100 mais recentes primeiro, a paginação por cima passa a operar só
                // dentro desse conjunto já limitado.
                $recentIds = SearchHistory::query()
                    ->where('user_id', Auth::id())
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->pluck('id');

                return SearchHistory::query()->whereIn('id', $recentIds);
            })
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
