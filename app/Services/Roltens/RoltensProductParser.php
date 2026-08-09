<?php

namespace App\Services\Roltens;

/**
 * Parses two different Roltens page types on two different hostnames:
 *
 * - The listing API (catalogo.roltens.com.br/produtos/listagem?page=N) —
 *   plain JSON (a jQuery bootgrid backend), confirmed live: `codigo`,
 *   `descricao`, `grupo`, a `foto` field shaped "{imagem},{thumb}" (both the
 *   same URL in every sample seen), a `saibamais` field shaped
 *   "Saiba mais,{detail_url}" carrying the FULL absolute detail-page URL —
 *   no need to construct it from a slug, just reuse it — and `total`.
 * - The detail page (roltens.com.br/produto/{codigo}) — plain server-rendered
 *   HTML, confirmed live: cross-reference/OEM codes only live here (not in
 *   the listing at all), as zero or more `<li><b>Cód. original:</b> {code}</li>`
 *   entries under an "Referências:" heading — a real product had 6 of them.
 *   An "Aplicação:" heading/list exists alongside it but was empty on every
 *   sampled product; extracted generically (raw `<li>` text) in case some
 *   product does populate it.
 */
final class RoltensProductParser
{
    /**
     * @return array{listings: array<int, array{codigo: string, descricao: string, grupo: ?string, imagem_url: ?string, url_produto: ?string}>, total: ?int}
     */
    public static function extractListing(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return ['listings' => [], 'total' => null];
        }

        $listings = collect($decoded['rows'] ?? [])
            ->filter(fn (array $row) => filled($row['codigo'] ?? null))
            ->map(fn (array $row) => [
                'codigo' => (string) $row['codigo'],
                'descricao' => (string) ($row['descricao'] ?? ''),
                'grupo' => filled($row['grupo'] ?? null) ? $row['grupo'] : null,
                'imagem_url' => self::csvPart($row['foto'] ?? null, 0),
                'url_produto' => self::csvPart($row['saibamais'] ?? null, 1),
            ])
            ->values()
            ->all();

        return [
            'listings' => $listings,
            'total' => isset($decoded['total']) ? (int) $decoded['total'] : null,
        ];
    }

    /**
     * @return array{conversoes: ?array<int, string>, aplicacao: ?string}
     */
    public static function extractDetail(string $html): array
    {
        return [
            'conversoes' => self::extractConversoes($html),
            'aplicacao' => self::extractAplicacao($html),
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private static function extractConversoes(string $html): ?array
    {
        preg_match_all('/<li><b>C[oó]d\. original:<\/b>\s*([^<]*)<\/li>/u', $html, $matches);

        $codigos = collect($matches[1] ?? [])
            ->map(fn (string $codigo) => trim($codigo))
            ->filter()
            ->unique()
            ->values();

        return $codigos->isNotEmpty() ? $codigos->all() : null;
    }

    private static function extractAplicacao(string $html): ?string
    {
        if (! preg_match('/<h5>Aplica[çc][ãa]o:<\/h5>\s*<ul[^>]*>(.*?)<\/ul>/su', $html, $block)) {
            return null;
        }

        preg_match_all('/<li[^>]*>(.*?)<\/li>/s', $block[1], $items);

        $values = collect($items[1] ?? [])
            ->map(fn (string $item) => trim(strip_tags($item)))
            ->filter()
            ->values();

        return $values->isNotEmpty() ? $values->implode(', ') : null;
    }

    private static function csvPart(?string $value, int $index): ?string
    {
        if (blank($value)) {
            return null;
        }

        $parts = explode(',', $value);

        return isset($parts[$index]) && trim($parts[$index]) !== '' ? trim($parts[$index]) : null;
    }
}
