<?php

namespace App\Filament\Resources\Catalogs\Schemas;

use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Rules\ValidJsonl;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

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
                Textarea::make('descricao')
                    ->label('Descrição')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('file')
                    ->required()
                    ->rules(['extensions:jsonl', new ValidJsonl])
                    ->directory('catalogs')
                    ->disabled(fn (?Catalog $record): bool => $record !== null && $record->import_status !== ImportStatus::NotImported)
                    ->helperText(fn (?Catalog $record): ?string => match (true) {
                        $record === null => null,
                        $record->import_status === ImportStatus::Imported => 'Não é possível anexar um novo arquivo enquanto as peças importadas existirem. Exclua as peças deste catálogo (na listagem de catálogos) para liberar este campo.',
                        $record->import_status === ImportStatus::Importing => 'Não é possível anexar um novo arquivo enquanto a importação estiver em andamento.',
                        default => null,
                    }),
                DatePicker::make('extracted_at')
                    ->required(),
                Toggle::make('is_active')
                    ->required()
                    ->disabled(fn (?Catalog $record): bool => $record?->import_status !== ImportStatus::Imported)
                    ->helperText(fn (?Catalog $record): string => match (true) {
                        $record === null => 'Só pode ser ativado depois que a importação for executada.',
                        $record->import_status === ImportStatus::NotImported => 'Não é possível ativar: a importação deste catálogo ainda não foi executada.',
                        $record->import_status === ImportStatus::Importing => 'Não é possível ativar: a importação está em andamento.',
                        default => 'Catálogo importado — você pode ativá-lo ou desativá-lo.',
                    }),
            ]);
    }
}
