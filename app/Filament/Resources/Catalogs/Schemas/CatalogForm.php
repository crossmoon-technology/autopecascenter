<?php

namespace App\Filament\Resources\Catalogs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class CatalogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('manufacturer_id')
                    ->relationship('manufacturer', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                FileUpload::make('file')
                    ->required()
                    ->rules(['extensions:jsonl'])
                    ->directory('catalogs'),
                DatePicker::make('extracted_at')
                    ->required(),
                Toggle::make('is_active')
                    ->required()
                    ->disabledOn(Operation::Create)
                    ->helperText('Só pode ser ativado depois que a importação for executada.'),
            ]);
    }
}
