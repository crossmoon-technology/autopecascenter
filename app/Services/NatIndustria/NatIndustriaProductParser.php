<?php

namespace App\Services\NatIndustria;

/**
 * Parses natindustria.com.br — a WordPress site (custom "produto" post
 * type, confirmed live NOT exposed via the REST API, but fully enumerable
 * through WordPress's own native XML sitemap: /wp-sitemap-posts-produto-1.xml,
 * confirmed live: 1036 URLs, one page, each with its own <lastmod>).
 *
 * NAT sells vehicle-fit accessories (shift boots/knobs, armrests, air
 * diffusers — comfort/interior parts, not parts with OEM cross-reference
 * numbers) — confirmed live, no cross-reference data exists anywhere on the
 * site at all.
 *
 * The tricky part: one WordPress "produto" post can represent MULTIPLE
 * distinct sellable SKUs — one per color swatch shown on the page — and
 * which side carries the numeric code is NOT consistent:
 * - Single-variant posts repeat the SAME code in both the page title
 *   ("difusor de ar - 200605") and its one color swatch ("200605 - LPRETO
 *   C/ BOTÃO PRATA").
 * - Multi-variant posts (confirmed live: "Apoio de Braço Golf") have NO
 *   code in the title at all — each of its 3 swatches carries its OWN
 *   distinct code instead ("Preto c/Linha Preta - 100700", "Cinza c/Linha
 *   Cinza - 100701", "Grafite c/Linha Grafite - 100702" — 3 real, different
 *   products).
 * - At least one sampled post ("coifa com manopla - 100169A") has its ONLY
 *   code in the title, with a codeless swatch caption ("Grafite c/Linha
 *   Grafite").
 *
 * extractDetail() handles all three shapes uniformly: try to pull a code
 * out of EACH swatch's own text first; any swatch that has none falls back
 * to the page title's own code instead (only correct when there's exactly
 * one swatch, which is the only case it's ever missing in).
 */
final class NatIndustriaProductParser
{
    /**
     * @return array<int, array{url: string, lastmod: ?string}>
     */
    public static function extractSitemap(string $xml): array
    {
        preg_match_all('/<url><loc>(.*?)<\/loc>(?:<lastmod>(.*?)<\/lastmod>)?<\/url>/', $xml, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $match) => ['url' => $match[1], 'lastmod' => $match[2] ?? null])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{codigo: string, descricao: string, aplicacao: ?string, imagem_url: ?string}>
     */
    public static function extractDetail(string $body): array
    {
        if (! preg_match('/<h1 class="single-product_title">(.*?)<\/h1>/s', $body, $titleMatch)) {
            return [];
        }

        [$baseDescricao, $titleCode] = self::splitTitle(trim(html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5)));
        $imagem = self::extractImage($body);
        $content = self::extractContentBlock($body);
        $aplicacao = self::extractAplicacao($content);
        $swatches = self::extractSwatches($content);

        if ($swatches === []) {
            return $titleCode !== null
                ? [['codigo' => $titleCode, 'descricao' => $baseDescricao, 'aplicacao' => $aplicacao, 'imagem_url' => $imagem]]
                : [];
        }

        return collect($swatches)
            ->map(function (string $swatchText) use ($baseDescricao, $titleCode, $aplicacao, $imagem) {
                [$cor, $swatchCode] = self::splitSwatch($swatchText);
                $codigo = $swatchCode ?? $titleCode;

                if ($codigo === null) {
                    return null;
                }

                return [
                    'codigo' => $codigo,
                    'descricao' => $cor !== '' ? "{$baseDescricao} - {$cor}" : $baseDescricao,
                    'aplicacao' => $aplicacao,
                    'imagem_url' => $imagem,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function splitTitle(string $title): array
    {
        if (preg_match('/^(.*?)\s*[-–]\s*([A-Za-z0-9]*\d[A-Za-z0-9]*)$/u', $title, $match)) {
            return [trim($match[1]), trim($match[2])];
        }

        return [$title, null];
    }

    /**
     * A entrada da amostra ("400497 - GRAFITE") tem o código ANTES do
     * hífen; outras ("Preto c/Linha Preta - 100700") tem ele DEPOIS — por
     * isso extrai o primeiro token majoritariamente numérico em vez de
     * assumir uma posição fixa.
     *
     * @return array{0: string, 1: ?string}
     */
    private static function splitSwatch(string $text): array
    {
        if (! preg_match('/\b(\d{4,}[A-Za-z]{0,2})\b/', $text, $match)) {
            return [trim($text), null];
        }

        $cor = trim(str_replace($match[1], '', $text), " \t\n\r\0\x0B-");

        return [$cor, $match[1]];
    }

    private static function extractContentBlock(string $body): string
    {
        if (! preg_match('/single-product_content">(.*?)(?:widget-shared|$)/s', $body, $match)) {
            return '';
        }

        return $match[1];
    }

    private static function extractAplicacao(string $content): ?string
    {
        $beforeFirstDiv = explode('<div', $content, 2)[0];
        $text = html_entity_decode(strip_tags($beforeFirstDiv), ENT_QUOTES | ENT_HTML5);

        $lines = collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->values();

        return $lines->isNotEmpty() ? $lines->implode(', ') : null;
    }

    /**
     * @return array<int, string>
     */
    private static function extractSwatches(string $content): array
    {
        preg_match_all('/<p[^>]*>(.*?)<\/p>/s', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $swatch) => trim(html_entity_decode(strip_tags($swatch), ENT_QUOTES | ENT_HTML5)))
            ->filter()
            ->values()
            ->all();
    }

    private static function extractImage(string $body): ?string
    {
        return preg_match('/class="product-image" src="([^"]*)"/', $body, $match) === 1 ? $match[1] : null;
    }
}
