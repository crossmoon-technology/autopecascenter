<?php

namespace App\Filament\Resources\Manufacturers\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ManufacturerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, Set $set, ?string $state) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->acceptedFileTypes(['image/png'])
                    ->maxSize(2048)
                    ->directory('manufacturers/logos'),
                FileUpload::make('icon')
                    ->image()
                    ->disk('public')
                    ->acceptedFileTypes(['image/png'])
                    ->maxSize(2048)
                    ->directory('manufacturers/icons'),
                TextInput::make('external_link'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
