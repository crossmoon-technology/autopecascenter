<?php

namespace App\Filament\Resources\Manufacturers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ManufacturerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('cnpj')
                    ->required()
                    ->numeric()
                    ->length(14)
                    ->unique(ignoreRecord: true),
                TextInput::make('logo'),
                TextInput::make('icon'),
                TextInput::make('external_link'),
            ]);
    }
}
