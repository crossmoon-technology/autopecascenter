<?php

namespace App\Services\BuscaNaRede;

/**
 * Parses buscanarede.com.br — a shared, multi-tenant catalog platform
 * (confirmed live: "Busca na Rede" itself is the vendor; each brand gets a
 * URL slug, e.g. LINMAX at /linmaxbrasil/produtos — the same generic scraper
 * should work for any other tenant on this platform, just a different
 * `$baseUrl`, same as C123CatalogScraper's approach elsewhere in this app).
 *
 * The listing (`/produtos`, `/produtos/{page}` for page 2+) already embeds
 * everything except cross-reference codes directly in each product card:
 * codigo/descrição/imagem via `data-codigo`/`data-titulo`/`data-imagem`
 * attributes on that card's "comparar" (compare) button, and a full vehicle
 * application table (montadora/modelo/versão/ano) right there too — no
 * detail-page fetch needed for any of that.
 *
 * Cross-reference codes are the one thing NOT in the listing: they live
 * behind two separate AJAX tabs on each product's own detail page —
 * `{detailUrl}/equivalences` (aftermarket brand equivalents, e.g. "DAYCO:
 * KTB255") and `{detailUrl}/oem` (original manufacturer numbers, e.g.
 * "Volkswagen - 030198119A") — confirmed live, both public, no session
 * required, both shaped as `<span class="label">{code}</span>` wrapping
 * every actual code, which is all extractConversoes() needs.
 */
final class BuscaNaRedeProductParser
{
    /**
     * @return array<int, array{codigo: string, descricao: string, imagem_url: ?string, url_produto: ?string, aplicacao: ?string}>
     */
    public static function extractListing(string $body): array
    {
        return collect(explode('card-content card-top', $body))
            ->skip(1)
            ->map(fn (string $chunk) => self::extractCard($chunk))
            ->filter()
            ->values()
            ->all();
    }

    public static function extractTotal(string $body): ?int
    {
        return preg_match('/<b>(\d+)<\/b>\s*<span>produtos encontrados/u', $body, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * @return array<int, string>|null
     */
    public static function extractConversoes(string $equivalencesBody, string $oemBody): ?array
    {
        preg_match_all('/<span class="label">(.*?)<\/span>/s', $equivalencesBody.$oemBody, $matches);

        $codes = collect($matches[1] ?? [])
            ->map(fn (string $code) => trim(self::decode($code)))
            ->filter()
            ->unique()
            ->values();

        return $codes->isNotEmpty() ? $codes->all() : null;
    }

    /**
     * @return array{codigo: string, descricao: string, imagem_url: ?string, url_produto: ?string, aplicacao: ?string}|null
     */
    private static function extractCard(string $chunk): ?array
    {
        if (! preg_match('/data-codigo="([^"]*)"/', $chunk, $codigoMatch) || blank($codigoMatch[1])) {
            return null;
        }

        return [
            'codigo' => self::decode($codigoMatch[1]),
            'descricao' => preg_match('/data-titulo="([^"]*)"/', $chunk, $m) ? trim(self::decode($m[1])) : '',
            'imagem_url' => preg_match('/data-imagem="([^"]*)"/', $chunk, $m) && filled($m[1]) ? self::decode($m[1]) : null,
            'url_produto' => preg_match('/href="([^"]*)" rel="page"/', $chunk, $m) && filled($m[1]) ? $m[1] : null,
            'aplicacao' => self::extractAplicacaoFromCard($chunk),
        ];
    }

    private static function extractAplicacaoFromCard(string $chunk): ?string
    {
        if (! preg_match('/<table class="table table-hover">(.*?)<\/table>/s', $chunk, $match)) {
            return null;
        }

        $html = preg_replace('/<\/tr>/', "\n", $match[1]);
        // Tags viram espaço, não string vazia — senão "<td>X</td><td>Y</td>"
        // colaria em "XY" sem separador nenhum.
        $text = self::decode(trim(preg_replace('/<[^>]+>/', ' ', $html)));

        $lines = collect(preg_split('/\n/', $text))
            ->map(fn (string $line) => trim(preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode(', ') : null;
    }

    private static function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }
}
