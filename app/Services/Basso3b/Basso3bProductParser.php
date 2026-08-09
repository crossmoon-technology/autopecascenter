<?php

namespace App\Services\Basso3b;

/**
 * Parses 3bcatalogo.basso.com.ar (Válvulas 3b/BBB, made by Argentina's BASSO
 * S.A.) — a genuinely new ASP.NET MVC platform, confirmed live via
 * `<title>...- BASSO S.A.</title>` and its distinctive `/Articulo/Details/{id}`
 * routes; not related to any other platform already handled in this app.
 *
 * The listing page (`/NroBasso?page=N&FamiliaId=102` — 102/"VALVULAS" is the
 * only option in the site's own filter dropdown, confirmed live, so it's the
 * entire catalog, not one category among several) only carries a table of
 * codigo + raw dimension columns per row — no description, application, or
 * cross-reference data at all. That only shows up on each product's own
 * `/Articulo/Details/{id}` detail page, confirmed live: an "APLICACIONES"
 * table (Motor/Marca/Modelo/Años) and an "INTERCAMBIOS" table
 * (Fabricante/Intercambio — the cross-reference codes) — hence this platform
 * needing a per-product detail fetch, same two-stage shape as ATE/Kaer
 * elsewhere in this app.
 *
 * There is no richer product name anywhere on the site (every product's own
 * "Tipo Prod" field is just "Válvulas", confirmed live across several
 * detail pages) — descricao falls back to codigo itself, same convention
 * MsMotorserviceProductParser uses when a source has no dedicated name field.
 */
final class Basso3bProductParser
{
    /**
     * @return array<int, array{id: int, codigo: string}>
     */
    public static function extractListing(string $html): array
    {
        preg_match_all('/<a href="\/Articulo\/Details\/(\d+)">([^<]*)<\/a>/', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $m): array => [
                'id' => (int) $m[1],
                'codigo' => trim(self::decode($m[2])),
            ])
            ->filter(fn (array $item): bool => $item['id'] > 0 && $item['codigo'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{codigo: string, descricao: string, grupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_url: ?string}|null
     */
    public static function extractDetail(string $html): ?array
    {
        if (! preg_match('/<dt>\s*Producto\s*<\/dt>\s*<dd>\s*([^<]*?)\s*<\/dd>/s', $html, $match)) {
            return null;
        }

        $codigo = trim(self::decode($match[1]));

        if ($codigo === '') {
            return null;
        }

        $grupo = preg_match('/<dt>\s*Tipo Prod\s*<\/dt>\s*<dd>\s*([^<]*?)\s*<\/dd>/s', $html, $m) ? trim(self::decode($m[1])) : '';

        return [
            'codigo' => $codigo,
            'descricao' => $codigo,
            'grupo' => $grupo !== '' ? $grupo : null,
            'aplicacao' => self::extractAplicacao($html),
            'conversoes' => self::extractConversoes($html, $codigo),
            'imagem_url' => self::extractImagem($html),
        ];
    }

    private static function extractAplicacao(string $html): ?string
    {
        if (! preg_match('/id="aplicacionesModelos".*?<table[^>]*>(.*?)<\/table>/s', $html, $tableMatch)) {
            return null;
        }

        preg_match_all('/<tr>(.*?)<\/tr>/s', $tableMatch[1], $rows);

        $lines = collect($rows[1])
            ->map(fn (string $row) => self::rowCells($row))
            ->filter(fn ($cells) => count($cells) >= 5)
            ->map(function (array $cells): ?string {
                [, $motor, $marca, $modelo, $anos] = $cells;

                $titulo = trim("{$marca} {$modelo}");
                $details = collect([$motor, $anos])->filter(fn (string $v): bool => $v !== '' && trim($v, "- \t\n\r") !== '');

                return $details->isNotEmpty() ? "{$titulo} ({$details->implode(', ')})" : ($titulo !== '' ? $titulo : null);
            })
            ->filter()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode(', ') : null;
    }

    /**
     * @return array<int, string>|null
     */
    private static function extractConversoes(string $html, string $codigo): ?array
    {
        if (! preg_match('/id="fabricantes".*?<table[^>]*>(.*?)<\/table>/s', $html, $tableMatch)) {
            return null;
        }

        preg_match_all('/<tr>(.*?)<\/tr>/s', $tableMatch[1], $rows);

        $normalizedCodigo = self::normalizeToken($codigo);

        $codes = collect($rows[1])
            ->map(fn (string $row) => self::rowCells($row))
            ->filter(fn ($cells) => count($cells) >= 2)
            ->map(fn (array $cells): string => self::normalizeToken($cells[1]))
            ->filter(fn (string $c): bool => $c !== '' && $c !== $normalizedCodigo)
            ->unique()
            ->values();

        return $codes->isNotEmpty() ? $codes->all() : null;
    }

    private static function extractImagem(string $html): ?string
    {
        if (! preg_match('/id="zoom_01"\s+src="([^"]*)"/', $html, $m) || blank($m[1])) {
            return null;
        }

        return trim($m[1]);
    }

    /**
     * @return array<int, string>
     */
    private static function rowCells(string $row): array
    {
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells);

        return collect($cells[1] ?? [])
            ->map(fn (string $cell) => trim(self::decode(preg_replace('/<[^>]+>/', ' ', $cell))))
            ->all();
    }

    private static function normalizeToken(string $value): string
    {
        return trim($value);
    }

    private static function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }
}
