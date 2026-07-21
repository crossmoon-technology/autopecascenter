<?php

namespace App\Filament\Resources\Parts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('catalog_id')
                    ->relationship('catalog', 'name')
                    ->required(),
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
                    ->helperText('Demais campos vindos do catálogo do fabricante, em JSON — variam de fornecedor para fornecedor. Ex: {"descricao": "MOLA A GÁS", "posicao": "PORTA MALAS"}')
                    ->afterStateHydrated(function (Textarea $component, mixed $state): void {
                        $component->state(
                            is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state
                        );
                    })
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null),
            ]);
    }
}
