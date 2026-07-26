<?php

namespace App\Filament\Resources\Catalogs\Tables;

use App\Jobs\ImportCatalogParts;
use App\Jobs\ImportCatalogPartsUpdate;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Rules\ValidJsonl;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CatalogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll(fn (): ?string => Catalog::query()
                ->where('import_status', ImportStatus::Importing)
                ->exists()
                    ? '3s'
                    : null)
            ->columns([
                ImageColumn::make('manufacturer.icon')
                    ->disk('public')
                    ->imageHeight(25)
                    ->label('Fabricante')
                    ->alignCenter(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->alignLeft(),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignLeft(),
                TextColumn::make('extracted_at')
                    ->label('Extraído em')
                    ->date()
                    ->sortable()
                    ->alignCenter(),
                ViewColumn::make('import_status')
                    ->label('Importação')
                    ->view('filament.tables.columns.catalog-import-status')
                    ->alignCenter(),
                ToggleColumn::make('is_active')
                    ->label('Ativo')
                    ->disabled(fn (Catalog $record): bool => $record->import_status !== ImportStatus::Imported)
                    ->tooltip(fn (Catalog $record): ?string => match ($record->import_status) {
                        ImportStatus::NotImported => 'Não é possível ativar: a importação deste catálogo ainda não foi executada.',
                        ImportStatus::Importing => 'Não é possível ativar: a importação está em andamento.',
                        ImportStatus::Imported => null,
                    })
                    ->alignCenter(),
                TextColumn::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
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
                    ->visible(fn (Catalog $record): bool => $record->import_status === ImportStatus::NotImported)
                    ->action(function (Catalog $record) {
                        $record->forceFill(['import_status' => ImportStatus::Importing])->save();

                        ImportCatalogParts::dispatch($record);

                        Notification::make()
                            ->title('Importação iniciada')
                            ->body('As peças serão processadas em segundo plano.')
                            ->success()
                            ->send();
                    }),
                Action::make('uploadUpdate')
                    ->label('Subir atualizações')
                    ->icon(Heroicon::OutlinedArrowUpOnSquareStack)
                    ->visible(fn (Catalog $record): bool => $record->import_status === ImportStatus::Imported)
                    ->schema([
                        FileUpload::make('file')
                            ->label('Arquivo de atualização')
                            ->required()
                            ->rules(['extensions:jsonl', new ValidJsonl])
                            ->disk('local')
                            ->directory('catalogs/updates')
                            ->helperText('Mesmo formato jsonl do arquivo original. Peças com um código que já existe nesse catálogo são ignoradas — só as novas são adicionadas.'),
                    ])
                    ->action(function (Catalog $record, array $data): void {
                        $record->forceFill(['import_status' => ImportStatus::Importing])->save();

                        ImportCatalogPartsUpdate::dispatch($record, $data['file']);

                        Notification::make()
                            ->title('Atualização iniciada')
                            ->body('As peças novas serão processadas em segundo plano — códigos já existentes são ignorados.')
                            ->success()
                            ->send();
                    }),
                Action::make('deleteParts')
                    ->label('Excluir peças')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Catalog $record): bool => $record->import_status === ImportStatus::Imported && $record->parts()->exists())
                    ->action(function (Catalog $record) {
                        // forceDelete, não delete: essa ação existe pra liberar o campo de
                        // arquivo pra reimportação, e o unique(catalog_id, codigo) ainda
                        // bloquearia os mesmos códigos se as peças só fossem soft-deletadas.
                        $record->parts()->forceDelete();
                        $record->update(['is_active' => false]);
                        $record->forceFill(['import_status' => ImportStatus::NotImported])->save();

                        Notification::make()
                            ->title('Peças excluídas')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
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
