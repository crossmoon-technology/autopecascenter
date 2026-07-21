<?php

namespace App\Filament\Pages\Buscas\CatalogDatabaseSearch\Enums;

use Filament\Support\Contracts\HasLabel;

enum SearchType: string implements HasLabel
{
    case Todos = 'todos';
    case Codigo = 'codigo';
    case Equivalentes = 'equivalentes';
    case Atributos = 'atributos';

    public function getLabel(): string
    {
        return match ($this) {
            self::Todos => 'Todos',
            self::Codigo => 'Código da peça',
            self::Equivalentes => 'Equivalentes',
            self::Atributos => 'Atributos',
        };
    }
}
