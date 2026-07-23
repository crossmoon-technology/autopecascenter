<?php

namespace App\Filament\Resources\Informativos\Tables;

use App\Filament\Resources\Informativos\InformativoResource;
use App\Models\Catalog;
use App\Models\Informativo;
use App\Models\Informativo\Enums\InformativoType;
use App\Models\Manufacturer;
use App\Services\Informativo\BulkCreateInformativos;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class InformativosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('catalog.manufacturer.logo')
                    ->disk('public')
                    ->imageHeight(15)
                    ->label('Fabricante')
                    ->alignCenter(),
                TextColumn::make('catalog.name')
                    ->label('Catálogo')
                    ->searchable()
                    ->alignCenter(),
                ViewColumn::make('file')
                    ->label('Preview')
                    ->view('filament.tables.columns.informativo-preview')
                    ->url(fn (Informativo $record): string => Storage::disk('public')->url($record->file))
                    ->openUrlInNewTab()
                    ->alignCenter(),
                TextColumn::make('original_name')
                    ->label('Arquivo')
                    ->icon(fn (Informativo $record): string => $record->type === InformativoType::Pdf
                        ? 'heroicon-o-document-text'
                        : 'heroicon-o-photo')
                    ->url(fn (Informativo $record): string => Storage::disk('public')->url($record->file))
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (InformativoType $state): string => $state->label())
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime()
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('catalog_id')
                    ->label('Catálogo')
                    ->relationship('catalog', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('manufacturer_id')
                    ->label('Fabricante')
                    ->options(fn (): array => Manufacturer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $value) => $query->whereHas('catalog', fn (Builder $query) => $query->where('manufacturer_id', $value)),
                    )),
            ])
            ->headerActions([
                Action::make('addInformativos')
                    ->label('Adicionar informativos')
                    ->icon('heroicon-o-plus')
                    // Ação customizada — diferente do CreateAction padrão do Filament,
                    // não checa a policy sozinha (ver App\Policies\InformativoPolicy),
                    // então precisa dessa checagem explícita.
                    ->visible(fn (): bool => InformativoResource::canCreate())
                    ->schema([
                        Select::make('catalog_id')
                            ->label('Catálogo')
                            ->relationship('catalog', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        FileUpload::make('files')
                            ->label('Arquivos (PDF ou imagem)')
                            ->multiple()
                            ->previewable(false)
                            ->disk('public')
                            ->directory('catalogs/informativos')
                            ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('original_file_names')
                            ->required()
                            ->helperText('Selecione um ou mais arquivos para enviar de uma vez.'),
                    ])
                    ->action(function (array $data): void {
                        $catalog = Catalog::query()->findOrFail($data['catalog_id']);

                        app(BulkCreateInformativos::class)->handle(
                            $catalog,
                            $data['files'],
                            $data['original_file_names'] ?? [],
                        );
                    }),
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
