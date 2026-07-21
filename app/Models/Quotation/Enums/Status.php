<?php

namespace App\Models\Quotation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Closed => 'Salva',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Closed => 'success',
        };
    }
}
