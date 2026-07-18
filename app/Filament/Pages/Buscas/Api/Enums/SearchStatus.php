<?php

namespace App\Filament\Pages\Buscas\Api\Enums;

enum SearchStatus: string
{
    case Loading = 'loading';
    case Success = 'success';
    case Failed = 'failed';
}
