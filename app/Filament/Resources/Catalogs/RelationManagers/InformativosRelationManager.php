<?php

namespace App\Filament\Resources\Catalogs\RelationManagers;

use App\Models\Informativo;
use App\Models\Informativo\Enums\InformativoType;
use App\Services\Informativo\BulkCreateInformativos;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class InformativosRelationManager extends RelationManager
{
    protected static string $relationship = 'informativos';

    protected static ?string $title = 'Informativos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
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
            ->headerActions([
                Action::make('addInformativos')
                    ->label('Adicionar informativos')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        FileUpload::make('files')
                            ->label('Arquivos (PDF ou imagem)')
                            ->multiple()
                            ->previewable(false)
                            ->disk('public')
                            ->directory('catalogs/informativos')
                            ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(25600)
                            ->storeFileNamesIn('original_file_names')
                            ->required()
                            ->helperText('Selecione um ou mais arquivos para enviar de uma vez.'),
                    ])
                    ->action(function (array $data): void {
                        app(BulkCreateInformativos::class)->handle(
                            $this->getOwnerRecord(),
                            $data['files'],
                            $data['original_file_names'] ?? [],
                        );
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
