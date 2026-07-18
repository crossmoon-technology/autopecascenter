<?php

namespace App\Models\Catalog\Enums;

enum ImportStatus: int
{
    case NotImported = 1;
    case Importing = 2;
    case Imported = 3;

    public function label(): string
    {
        return match ($this) {
            self::NotImported => 'Não importado',
            self::Importing => 'Importando',
            self::Imported => 'Já importado',
        };
    }
}
