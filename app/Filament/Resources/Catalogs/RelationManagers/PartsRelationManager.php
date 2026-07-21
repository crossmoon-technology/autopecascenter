<?php

namespace App\Filament\Resources\Catalogs\RelationManagers;

use App\Models\Part;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartsRelationManager extends RelationManager
{
    protected static string $relationship = 'parts';

    protected static ?string $title = 'Peças';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo')
                    ->required(),
                Textarea::make('conversoes')
                    ->label('Conversões')
                    ->rows(5)
                    ->rule('json')
                    ->helperText('Mapa fabricante => códigos equivalentes, em JSON. Ex: {"MONROE": ["GS440"]}')
                    ->afterStateHydrated(function (Textarea $component, mixed $state): void {
                        $component->state(
                            is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state
                        );
                    })
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null),
                Textarea::make('atributos')
                    ->label('Atributos')
                    ->rows(5)
                    ->rule('json')
                    ->helperText('Demais campos vindos do catálogo do fabricante, em JSON.')
                    ->afterStateHydrated(function (Textarea $component, mixed $state): void {
                        $component->state(
                            is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state
                        );
                    })
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codigo')
            ->columns([
                TextColumn::make('codigo')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('atributos')
                    ->label('Atributos')
                    ->state(fn (Part $record): ?string => collect($record->atributos ?? [])
                        ->map(fn ($value, $key) => "{$key}: {$value}")
                        ->implode(', ') ?: null)
                    ->limit(60),
                TextColumn::make('created_at')
                    ->label('Importado em')
                    ->dateTime()
                    ->sortable()
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
