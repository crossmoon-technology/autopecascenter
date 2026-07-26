<?php

namespace App\Http\Controllers\Api\CatalogImportController\Exceptions;

use Illuminate\Validation\ValidationException;

class CatalogNotImportedException extends ValidationException
{
    public const MESSAGE = 'Este catálogo ainda não foi importado pela primeira vez — isso precisa ser feito pelo painel antes de enviar atualizações por aqui.';

    public static function make(): static
    {
        return static::withMessages([
            'slug' => self::MESSAGE,
        ]);
    }
}
