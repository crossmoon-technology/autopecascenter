<?php

namespace App\Filament\Resources\Catalogs\Tables;

use App\Jobs\ImportCatalogParts;
use App\Models\Catalog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CatalogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('manufacturer.icon')
                    ->disk('public')
                    ->label('Fabricante')
                    ->alignCenter(),
                TextColumn::make('name')
                    ->searchable()
                    ->alignLeft(),
                TextColumn::make('extracted_at')
                    ->date()
                    ->sortable()
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('import')
                    ->label('Importar')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->requiresConfirmation()
                    ->action(function (Catalog $record) {
                        ImportCatalogParts::dispatch($record);

                        Notification::make()
                            ->title('Importação iniciada')
                            ->body('As peças serão processadas em segundo plano.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
