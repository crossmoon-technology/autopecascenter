<?php

namespace App\Services\Kaer;

/**
 * Kaer's product titles consistently follow "{código OEM} - {código Kaer} - {descrição}"
 * (confirmed live across dozens of samples) — shared between the live search
 * provider and the bulk catalog scraper.
 */
final class KaerTitleParser
{
    /**
     * @return array{codigo: string, descricao: string}
     */
    public static function parse(string $title): array
    {
        [$codigo, , $descricao] = array_pad(explode(' - ', $title, 3), 3, '');
        $codigo = trim($codigo);
        $descricao = trim($descricao);

        return [
            'codigo' => $codigo !== '' ? $codigo : $title,
            'descricao' => $descricao !== '' ? $descricao : $title,
        ];
    }
}
