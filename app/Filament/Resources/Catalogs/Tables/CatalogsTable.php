<?php

namespace App\Filament\Resources\Catalogs\Tables;

use App\Enums\Role;
use App\Jobs\ImportCatalogParts;
use App\Jobs\ImportCatalogPartsFromUpload;
use App\Jobs\ImportCatalogPartsUpdate;
use App\Jobs\ScrapeCatalog;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Rules\ValidJsonl;
use App\Services\CatalogImport\ExportCatalogPartsToJsonl;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
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
                TextColumn::make('parts_count')
                    ->label('Peças')
                    ->counts('parts')
                    ->badge()
                    // 0 é um estado normal, não um erro — cobre tanto catálogos que
                    // nunca foram importados quanto os que já foram mas ficaram vazios.
                    ->color(fn (?int $state): string => filled($state) && $state > 0 ? 'success' : 'gray')
                    ->sortable()
                    ->alignCenter(),
                ToggleColumn::make('is_active')
                    ->label('Ativo')
                    ->disabled(fn (Catalog $record): bool => blank($record->scraper_slug) && $record->import_status !== ImportStatus::Imported)
                    ->tooltip(fn (Catalog $record): ?string => match (true) {
                        filled($record->scraper_slug) => null,
                        $record->import_status === ImportStatus::NotImported => 'Não é possível ativar: a importação deste catálogo ainda não foi executada.',
                        $record->import_status === ImportStatus::Importing => 'Não é possível ativar: a importação está em andamento.',
                        default => null,
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
                    ->visible(fn (Catalog $record): bool => $record->import_status === ImportStatus::NotImported && filled($record->file) && blank($record->scraper_slug))
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
                    ->visible(fn (Catalog $record): bool => $record->import_status === ImportStatus::Imported && blank($record->scraper_slug))
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
                Action::make('runScraper')
                    ->label('Rodar scraper')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->visible(fn (Catalog $record): bool => filled($record->scraper_slug) && $record->import_status !== ImportStatus::Importing)
                    ->action(function (Catalog $record) {
                        $record->forceFill(['import_status' => ImportStatus::Importing])->save();

                        ScrapeCatalog::dispatch($record);

                        Notification::make()
                            ->title('Scraping iniciado')
                            ->body('O catálogo será buscado na fonte e reimportado em segundo plano, caso haja mudanças.')
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
                // As duas actions abaixo existem pra levar peças já raspadas em um
                // ambiente (ex: aqui) pra outro (ex: produção) sem precisar rodar o
                // scraper de novo lá — ao contrário de import/uploadUpdate, NUNCA
                // gravam catalogs.file/update_file (ver App\Jobs\ImportCatalogPartsFromUpload)
                // e funcionam mesmo em catálogos com scraper_slug preenchido. Só pra
                // super admin: não há hoje nenhum mecanismo de restrição por role no
                // nível de resource nesse painel compartilhado, então a checagem fica
                // aqui mesmo, na própria action.
                Action::make('exportParts')
                    ->label('Exportar peças (.jsonl)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Catalog $record): bool => auth()->user()?->role === Role::SuperAdmin && $record->parts()->exists())
                    ->action(fn (Catalog $record): StreamedResponse => response()->streamDownload(
                        fn () => print (app(ExportCatalogPartsToJsonl::class)->execute($record)),
                        "{$record->slug}.jsonl",
                        ['Content-Type' => 'application/jsonl'],
                    )),
                Action::make('importSeed')
                    ->label('Importar (sem vincular arquivo)')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn (): bool => auth()->user()?->role === Role::SuperAdmin)
                    ->schema([
                        FileUpload::make('file')
                            ->label('Arquivo .jsonl')
                            ->required()
                            ->rules(['extensions:jsonl', new ValidJsonl])
                            ->disk('local')
                            ->directory('catalogs/seed-imports')
                            ->helperText('Mesmo formato jsonl do arquivo original. Diferente de "Importar"/"Subir atualizações", não fica vinculado a este catálogo — o arquivo é descartado depois de processado, e funciona mesmo em catálogos gerenciados por scraper.'),
                    ])
                    ->action(function (Catalog $record, array $data): void {
                        $record->forceFill(['import_status' => ImportStatus::Importing])->save();

                        ImportCatalogPartsFromUpload::dispatch($record, $data['file']);

                        Notification::make()
                            ->title('Importação iniciada')
                            ->body('As peças serão processadas em segundo plano, sem vincular este catálogo ao arquivo enviado.')
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
