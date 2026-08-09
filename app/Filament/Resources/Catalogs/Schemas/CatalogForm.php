<?php

namespace App\Filament\Resources\Catalogs\Schemas;

use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Rules\ValidJsonl;
use App\Services\CatalogScraping\CatalogScraperRegistry;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CatalogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('manufacturer_id')
                    ->label('Fabricante')
                    ->relationship('manufacturer', 'name')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, Set $set, ?string $state) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Identificador único usado pela API de importação de catálogo.'),
                Textarea::make('descricao')
                    ->label('Descrição')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('scraper_slug')
                    ->label('Provedor de scraping')
                    ->options(fn () => app(CatalogScraperRegistry::class)->options())
                    ->native(false)
                    ->live()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (Get $get): bool => filled($get('file')))
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            if (filled($value) && filled($get('file'))) {
                                $fail('Não é possível selecionar um provedor de scraping com um arquivo de importação já definido.');
                            }
                        },
                    ])
                    ->helperText(fn (Get $get): string => filled($get('file'))
                        ? 'Não é possível selecionar um provedor enquanto houver um arquivo de importação manual definido abaixo.'
                        : 'Opcional — cada provedor só pode ser usado por um catálogo. Se selecionado, o catálogo é buscado e reimportado automaticamente todo dia a partir da fonte configurada, e o arquivo abaixo passa a ser gerenciado pelo sistema em vez de enviado manualmente.'),
                FileUpload::make('file')
                    ->label('Arquivo original')
                    ->rules(['extensions:jsonl', new ValidJsonl])
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            if (filled($value) && filled($get('scraper_slug'))) {
                                $fail('Não é possível enviar um arquivo manual com um provedor de scraping já selecionado.');
                            }
                        },
                    ])
                    ->directory('catalogs')
                    ->live()
                    ->disabled(fn (?Catalog $record, Get $get): bool => filled($get('scraper_slug')) || ($record !== null && $record->import_status !== ImportStatus::NotImported))
                    ->helperText(fn (?Catalog $record, Get $get): ?string => match (true) {
                        filled($get('scraper_slug')) => 'Gerenciado automaticamente pelo provedor de scraping selecionado acima — não é possível enviar um arquivo manual enquanto um provedor estiver selecionado.',
                        $record === null => 'Opcional — sem arquivo, as peças podem ser enviadas depois pela API de importação de catálogo, ou selecione um provedor de scraping acima.',
                        $record->import_status === ImportStatus::Imported => 'Não é possível anexar um novo arquivo enquanto as peças importadas existirem. Exclua as peças deste catálogo (na listagem de catálogos) para liberar este campo.',
                        $record->import_status === ImportStatus::Importing => 'Não é possível anexar um novo arquivo enquanto a importação estiver em andamento.',
                        default => null,
                    }),
                // Os dois abaixo só existem pra exibição — nunca aparecem em criar/editar,
                // já que nenhum dos dois é preenchido pelo usuário através desse form
                // (import_status e update_file são geridos pelos jobs de importação).
                TextInput::make('import_status')
                    ->label('Importação')
                    ->disabled()
                    ->visible(fn (string $operation): bool => $operation === 'view')
                    // attributesToArray() (usado pelo fillForm do ViewRecord) já
                    // serializa o enum pro valor cru (int) antes de chegar aqui, não a
                    // instância — por isso o tryFrom, além do caso já-instância.
                    ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                        $status = $state instanceof ImportStatus ? $state : ImportStatus::tryFrom($state);

                        $component->state($status?->label() ?? $state);
                    }),
                FileUpload::make('update_file')
                    ->label('Última atualização')
                    ->disk('local')
                    ->disabled()
                    ->visible(fn (string $operation, ?Catalog $record): bool => $operation === 'view' && filled($record?->update_file)),
                DatePicker::make('extracted_at')
                    ->label('Extraído em')
                    ->helperText('Opcional — pode ser preenchido depois, quando o catálogo for importado.'),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->required()
                    ->disabled(fn (?Catalog $record, Get $get): bool => blank($get('scraper_slug')) && $record?->import_status !== ImportStatus::Imported)
                    ->helperText(fn (?Catalog $record, Get $get): string => match (true) {
                        filled($get('scraper_slug')) => 'Provedor de scraping selecionado — pode ser ativado mesmo antes da primeira importação automática.',
                        $record === null => 'Só pode ser ativado depois que a importação for executada, ou selecione um provedor de scraping acima.',
                        $record->import_status === ImportStatus::NotImported => 'Não é possível ativar: a importação deste catálogo ainda não foi executada.',
                        $record->import_status === ImportStatus::Importing => 'Não é possível ativar: a importação está em andamento.',
                        default => 'Catálogo importado — você pode ativá-lo ou desativá-lo.',
                    }),
            ]);
    }
}
