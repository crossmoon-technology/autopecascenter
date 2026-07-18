<?php

namespace App\Filament\Resources\Parts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('catalog.manufacturer.logo')
                    ->disk('public')
                    ->imageHeight(22)
                    ->label('Fabricante')
                    ->alignCenter(),
                TextColumn::make('catalog.name')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('codigo')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('descricao')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('tipo')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('posicao')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('categoria')
                    ->searchable()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
