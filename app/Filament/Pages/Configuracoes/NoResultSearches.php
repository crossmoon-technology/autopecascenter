<?php

namespace App\Filament\Pages\Configuracoes;

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
use UnitEnum;

class NoResultSearches extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.configuracoes.no-result-searches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceFrown;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Buscas sem resultado';

    protected static ?string $title = 'Buscas sem resultado';

    /**
     * Agregado entre todos os usuários (não só o usuário atual) — a ideia é dar pro
     * Admin/SuperAdmin um radar do que falta cadastrar, não uma lista pessoal. Só a
     * Base de dados grava de forma confiável se a busca achou algo ou não (a API
     * responde de forma assíncrona), então é a única fonte aqui por enquanto.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SearchHistory::query()
                ->selectRaw('min(id) as id')
                ->addSelect('query')
                ->selectRaw('count(*) as total')
                ->selectRaw('max(created_at) as last_searched_at')
                ->where('method', Method::Database)
                ->where('found_results', false)
                ->groupBy('query'))
            ->columns([
                TextColumn::make('query')
                    ->label('Termo buscado')
                    ->searchable(),
                TextColumn::make('total')
                    ->label('Vezes buscado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_searched_at')
                    ->label('Última vez')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('total', 'desc')
            // A query é agregada (GROUP BY query) — o desempate automático do Filament
            // por chave primária faria "order by search_histories.id", que quebra no
            // Postgres por não estar no GROUP BY nem numa função de agregação.
            ->defaultKeySort(false)
            ->emptyStateHeading('Nenhuma busca sem resultado até agora')
            ->emptyStateDescription('Quando um código buscado na Base de dados não achar nada, ele aparece aqui.')
            ->emptyStateIcon(Heroicon::OutlinedFaceFrown);
    }
}
