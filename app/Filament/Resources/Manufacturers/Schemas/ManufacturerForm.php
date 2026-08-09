<?php

namespace App\Filament\Resources\Manufacturers\Schemas;

use App\Services\PartSearch\PartSearchProviderRegistry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManufacturerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, Set $set, ?string $state) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('part_search_slug')
                    ->label('Provedor de busca ao vivo')
                    ->options(fn () => app(PartSearchProviderRegistry::class)->options())
                    ->native(false)
                    ->unique(ignoreRecord: true)
                    ->helperText('Opcional — só pra fabricantes sem raspagem em bloco viável (ver CatalogScraperRegistry/config/scrapers.php), onde a busca é feita ao vivo no site do fabricante a cada consulta, direto na página "Base de dados". Não precisa de catálogo nenhum cadastrado.'),
                FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                    ->maxSize(2048)
                    ->directory('manufacturers/logos')
                    ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('public')->delete($file)),
                FileUpload::make('icon')
                    ->image()
                    ->disk('public')
                    ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                    ->maxSize(2048)
                    ->directory('manufacturers/icons')
                    ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('public')->delete($file)),
                TextInput::make('external_link')
                    ->url()
                    ->nullable(),
                TextInput::make('iframe_url')
                    ->label('URL do iframe')
                    ->url()
                    ->nullable()
                    ->helperText('Página de busca do fabricante que pode ser embutida em iframe.'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
