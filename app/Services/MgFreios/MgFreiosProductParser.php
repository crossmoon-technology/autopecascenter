<?php

namespace App\Services\MgFreios;

/**
 * Parses loja.mgfreios.com.br (Tray Commerce platform) listing and detail
 * pages. Unlike the "C123" ASP engine or the "cw"/Ideia2001 engine used
 * elsewhere in this app, Tray conveniently embeds a Google Tag Manager
 * `dataLayer` JS array on every page — both the search/listing page
 * (busca.php, under a `listProducts` key) and each product's own detail page
 * — with clean structured fields (`reference` = part code, `brand` =
 * montadora, `breadcrumbDetails` = category hierarchy) instead of needing
 * fragile positional/free-text parsing for those (confirmed live, identical
 * shape on both page types, always a single-element array wrapping one
 * object).
 *
 * The one thing NOT in dataLayer: cross-reference/OEM codes. Those only show
 * up as a trailing "ORIG. <code>[ - <code> ...]" segment inside the
 * detail page's free-text "Descrição Geral" tab (confirmed live across 17
 * real products — normal product description text otherwise, with no other
 * structure to lean on, and not every product has one at all).
 */
final class MgFreiosProductParser
{
    /**
     * @return array{listings: array<int, array{codigo: string, descricao: string, fabricante: ?string, imagem_url: ?string, url_produto: ?string}>, total: ?int}
     */
    public static function extractListing(string $body): array
    {
        $data = self::extractDataLayer($body);

        if ($data === null) {
            return ['listings' => [], 'total' => null];
        }

        $listings = collect($data['listProducts'] ?? [])
            ->filter(fn (array $product) => filled($product['reference'] ?? null))
            ->map(fn (array $product) => [
                'codigo' => (string) $product['reference'],
                'descricao' => (string) ($product['nameProduct'] ?? ''),
                'fabricante' => filled($product['brand'] ?? null) ? $product['brand'] : null,
                'imagem_url' => filled($product['urlImage'] ?? null) ? $product['urlImage'] : null,
                'url_produto' => filled($product['urlProduct'] ?? null) ? $product['urlProduct'] : null,
            ])
            ->values()
            ->all();

        return [
            'listings' => $listings,
            'total' => isset($data['siteSearchResults']) ? (int) $data['siteSearchResults'] : null,
        ];
    }

    /**
     * @return array{grupo: ?string, subgrupo: ?string, conversoes: ?array<int, string>}
     */
    public static function extractDetail(string $body): array
    {
        $data = self::extractDataLayer($body);

        $levels = collect($data['breadcrumbDetails'] ?? [])
            ->filter(fn (array $breadcrumb) => filled($breadcrumb['name'] ?? null))
            ->sortBy('level')
            ->values();

        return [
            'grupo' => $levels->get(0)['name'] ?? null,
            'subgrupo' => $levels->get(1)['name'] ?? null,
            'conversoes' => self::extractConversoes($body),
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private static function extractConversoes(string $body): ?array
    {
        if (! preg_match('/board_htm description">(.*?)<\/div>/s', $body, $descricaoMatch)) {
            return null;
        }

        $descricao = trim(strip_tags($descricaoMatch[1]));

        if (! preg_match('/ORIG\.\s*(.+)$/isu', $descricao, $origMatch)) {
            return null;
        }

        $codigos = collect(explode(' - ', $origMatch[1]))
            ->map(fn (string $codigo) => trim($codigo, " \t\n\r\0\x0B,"))
            ->filter()
            ->values();

        return $codigos->isNotEmpty() ? $codigos->all() : null;
    }

    /**
     * Both listing and detail pages embed exactly one `dataLayer = [{...}]`
     * JS array — no trailing `;` guaranteed (confirmed live: the detail page
     * omits it, the listing page doesn't), so this reads up to the closing
     * `</script>` tag rather than relying on any particular terminator.
     *
     * @return array<string, mixed>|null
     */
    private static function extractDataLayer(string $body): ?array
    {
        $prefix = 'dataLayer = [';
        $start = strpos($body, $prefix);

        if ($start === false) {
            return null;
        }

        $end = strpos($body, '</script>', $start);

        if ($end === false) {
            return null;
        }

        $json = substr($body, $start + strlen('dataLayer = '), $end - ($start + strlen('dataLayer = ')));
        $decoded = json_decode(rtrim(trim($json), '; '), true);

        return is_array($decoded) ? ($decoded[0] ?? null) : null;
    }
}
