<?php

namespace App\Services\MgPecasAutomotivas;

/**
 * Parses mgpecasautomotivas.com.br — a Nuvemshop (Tiendanube) storefront.
 * Both the listing (/produtos?page=N) and detail pages embed clean
 * schema.org JSON-LD, in per-block `<script type="application/ld+json"
 * data-component='structured-data.TYPE'>` tags — the listing has one
 * `item`-type block per product (confirmed live: exactly 50, matching the
 * page's own product count); the detail page has several `item` blocks too
 * (related/upsell products, NOT the main one) but only one `page`-type
 * block, which carries both the breadcrumb (grupo/montadora hierarchy) and
 * the main product under `mainEntity` — extractDetail() only ever reads
 * the `page` block, never `item`, to avoid picking up a related product by
 * mistake.
 *
 * Cross-reference/OEM codes and vehicle application data are NOT in the
 * JSON-LD at all (its own `description` field is just generic marketing
 * boilerplate, e.g. "Compre online MG002 por R$0,00...") — they live in a
 * separate rich-text "Descrição" panel further down the detail page HTML
 * (confirmed live across 5 real products), consistently shaped as:
 * a first line naming the part type, a "Nº Original: {code}[ / {code} ...]"
 * line, an "Aplicação:" label, then one paragraph per compatible vehicle.
 */
final class MgPecasAutomotivasProductParser
{
    /**
     * @return array<int, array{codigo: string, imagem_url: ?string, url_produto: ?string}>
     */
    public static function extractListing(string $body): array
    {
        return collect(self::extractJsonLdBlocks($body, 'item'))
            ->filter(fn (array $data) => filled($data['name'] ?? null))
            ->map(fn (array $data) => [
                'codigo' => (string) $data['name'],
                'imagem_url' => filled($data['image'] ?? null) ? $data['image'] : null,
                'url_produto' => filled($data['offers']['url'] ?? null)
                    ? $data['offers']['url']
                    : (filled($data['mainEntityOfPage']['@id'] ?? null) ? $data['mainEntityOfPage']['@id'] : null),
            ])
            ->values()
            ->all();
    }

    public static function extractTotal(string $body): ?int
    {
        return preg_match('/LS\.productsCount\s*=\s*(\d+);/', $body, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * @return array{descricao: ?string, grupo: ?string, fabricante: ?string, conversoes: ?array<int, string>, aplicacao: ?string}
     */
    public static function extractDetail(string $body): array
    {
        $page = self::extractJsonLdBlocks($body, 'page')[0] ?? [];
        $breadcrumb = collect($page['breadcrumb']['itemListElement'] ?? [])->keyBy('position');
        $userContent = self::extractUserContent($body);

        return [
            'descricao' => $userContent['descricao'],
            'grupo' => filled($breadcrumb[2]['name'] ?? null) ? $breadcrumb[2]['name'] : null,
            'fabricante' => filled($breadcrumb[3]['name'] ?? null) ? $breadcrumb[3]['name'] : null,
            'conversoes' => $userContent['conversoes'],
            'aplicacao' => $userContent['aplicacao'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function extractJsonLdBlocks(string $body, string $type): array
    {
        preg_match_all(
            '/<script type="application\/ld\+json" data-component=\'structured-data\.'.preg_quote($type, '/').'\'>(.*?)<\/script>/s',
            $body,
            $matches
        );

        return collect($matches[1] ?? [])
            ->map(fn (string $json) => json_decode($json, true))
            ->filter(fn ($data) => is_array($data))
            ->values()
            ->all();
    }

    /**
     * @return array{descricao: ?string, conversoes: ?array<int, string>, aplicacao: ?string}
     */
    private static function extractUserContent(string $body): array
    {
        if (! preg_match('/user-content[^"]*">(.*?)<\/div>/s', $body, $match)) {
            return ['descricao' => null, 'conversoes' => null, 'aplicacao' => null];
        }

        // </p> vira quebra de linha ANTES de remover as demais tags — senão
        // "Nº Original" e o valor, cada um dentro do seu próprio <span>
        // aninhado na MESMA linha, virariam "linhas" separadas incorretamente.
        $html = preg_replace('/<\/p>|<br\s*\/?>/i', "\n", $match[1]);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        $lines = collect(preg_split('/\n/', $text))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->values();

        $descricao = $lines->get(0);
        $conversoes = null;
        $aplicacaoIndex = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^N[ºo°]?\.?\s*Original\s*:?\s*(.+)$/iu', $line, $codeMatch)) {
                $codes = collect(explode('/', $codeMatch[1]))
                    ->map(fn (string $code) => trim($code))
                    ->filter()
                    ->values();

                $conversoes = $codes->isNotEmpty() ? $codes->all() : null;
            }

            if ($aplicacaoIndex === null && preg_match('/^Aplica[cç][aã]o\s*:?\s*$/iu', $line) === 1) {
                $aplicacaoIndex = $index;
            }
        }

        $aplicacao = $aplicacaoIndex !== null ? $lines->slice($aplicacaoIndex + 1)->implode(', ') : '';

        return [
            'descricao' => $descricao,
            'conversoes' => $conversoes,
            'aplicacao' => $aplicacao !== '' ? $aplicacao : null,
        ];
    }
}
