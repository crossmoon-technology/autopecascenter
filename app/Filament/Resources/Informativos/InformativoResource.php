<?php

namespace App\Filament\Resources\Informativos;

use App\Filament\Resources\Informativos\Pages\EditInformativo;
use App\Filament\Resources\Informativos\Pages\ListInformativos;
use App\Filament\Resources\Informativos\Schemas\InformativoForm;
use App\Filament\Resources\Informativos\Tables\InformativosTable;
use App\Models\Informativo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InformativoResource extends Resource
{
    protected static ?string $model = Informativo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Megaphone;

    protected static ?string $navigationLabel = 'Informativos';

    protected static ?string $modelLabel = 'Informativo';

    protected static ?string $pluralModelLabel = 'Informativos';

    public static function form(Schema $schema): Schema
    {
        return InformativoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InformativosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInformativos::route('/'),
            'edit' => EditInformativo::route('/{record}/edit'),
        ];
    }
}
