<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * O default só se aplica quando a chave nunca foi salva — se a linha existe mas
     * value é null (campo explicitamente limpo), retorna null mesmo, sem cair no
     * default. Por isso aqui não dá pra usar `?? $default` direto sobre o valor: isso
     * confundiria "nunca preenchido" com "preenchido e depois apagado de propósito".
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting !== null ? $setting->value : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
