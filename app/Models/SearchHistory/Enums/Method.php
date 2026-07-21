<?php

namespace App\Models\SearchHistory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Method: string implements HasColor, HasLabel
{
    case Api = 'api';
    case Database = 'base';

    public function getLabel(): string
    {
        return match ($this) {
            self::Api => 'API',
            self::Database => 'Base de dados',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Api => 'info',
            self::Database => 'success',
        };
    }
}
