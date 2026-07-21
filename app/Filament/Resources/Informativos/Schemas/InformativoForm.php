<?php

namespace App\Filament\Resources\Informativos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InformativoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('catalog_id')
                    ->label('Catálogo')
                    ->relationship('catalog', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('original_name')
                    ->label('Nome do arquivo')
                    ->required(),
            ]);
    }
}
