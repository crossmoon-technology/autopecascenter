<?php

namespace App\Models\QuotationItem\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Source: string implements HasColor, HasLabel
{
    case Database = 'database';
    case Api = 'api';
    case Iframe = 'iframe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Database => 'Base de dados',
            self::Api => 'API',
            self::Iframe => 'Iframe',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Database => 'success',
            self::Api => 'info',
            self::Iframe => 'gray',
        };
    }
}
