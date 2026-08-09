<?php

namespace App\Services\Fersa;

use App\Services\C123\C123ProductParser;

/**
 * Maps one raw Algolia hit from Fersa Brasil's catalog index
 * (nke_pro_fersa_brazil_pt_products) into this app's product row shape.
 * Fersa's storefront distributes several third-party bearing brands under
 * one catalog (confirmed live: NKE, A&S, PFI, FERSA), so `manufacturer`
 * really does vary per product — it isn't always "Fersa".
 *
 * Cross-reference/OEM codes live in two fields, `fersa_cross_references`
 * and `fersa_cross_references_additional`, each a flat array mixing three
 * shapes (confirmed live):
 * - a bare brand name with no code at all (e.g. "JOHN DEERE", "OTHER") —
 *   not usable, distinguished from a real bare code (e.g. "MF80111303")
 *   by whether it contains a digit;
 * - a "{code}~{brand}" string — the code is the part before "~";
 * - a nested array of 2-3 formatting variants of the SAME code+brand (e.g.
 *   with/without spaces or dashes) — only the first variant is kept, to
 *   avoid bloating conversões with near-duplicate noise.
 *
 * Reuses C123ProductParser::normalizeConversoes() for the final uppercase/
 * dedupe/self-exclude pass rather than duplicating that logic a third time
 * in this app.
 */
final class FersaProductParser
{
    /**
     * @param  array<string, mixed>  $hit
     * @return array{codigo: string, descricao: string, conversoes: ?array<int, string>, fabricante: ?string, grupo: ?string, subgrupo: ?string, imagem_url: ?string}|null
     */
    public static function extractProduct(array $hit): ?array
    {
        $codigo = self::firstSku($hit['sku'] ?? null);

        if ($codigo === null) {
            return null;
        }

        $rawCodes = [
            ...self::flattenCrossReferences($hit['fersa_cross_references'] ?? []),
            ...self::flattenCrossReferences($hit['fersa_cross_references_additional'] ?? []),
        ];

        return [
            'codigo' => $codigo,
            'descricao' => (string) ($hit['name'] ?? $codigo),
            'conversoes' => C123ProductParser::normalizeConversoes($rawCodes, $codigo),
            'fabricante' => filled($hit['manufacturer'] ?? null) ? (string) $hit['manufacturer'] : null,
            'grupo' => self::firstOrNull($hit['categories']['level0'] ?? null),
            'subgrupo' => self::firstOrNull($hit['categories']['level1'] ?? null),
            'imagem_url' => filled($hit['image_url'] ?? null) ? (string) $hit['image_url'] : null,
        ];
    }

    private static function firstSku(mixed $sku): ?string
    {
        if (is_string($sku) && $sku !== '') {
            return $sku;
        }

        if (is_array($sku) && filled($sku[0] ?? null)) {
            return (string) $sku[0];
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, string>
     */
    private static function flattenCrossReferences(array $entries): array
    {
        return collect($entries)
            ->flatMap(function ($entry) {
                $representative = is_array($entry) ? ($entry[0] ?? null) : $entry;

                if (! is_string($representative) || $representative === '') {
                    return [];
                }

                $withoutBrand = str_contains($representative, '~') ? explode('~', $representative)[0] : $representative;

                // Reproduz um caso real: uma entrada pode ser uma lista de
                // VÁRIOS códigos diferentes separados por vírgula dentro da
                // MESMA string (não um array aninhado nem "CÓDIGO~MARCA") —
                // um produto real tinha 16 códigos assim concatenados, que
                // sem esse split viravam um único "código" de mais de 255
                // caracteres e estouravam a coluna no banco.
                return collect(explode(',', $withoutBrand))
                    ->map(fn (string $code) => trim($code))
                    // Pedaços sem nenhum dígito são só o nome de uma marca
                    // sem código nenhum (ex: "JOHN DEERE", "OTHER") — não
                    // servem como cross-reference.
                    ->filter(fn (string $code) => $code !== '' && preg_match('/\d/', $code) === 1)
                    ->all();
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function firstOrNull(mixed $value): ?string
    {
        if (is_array($value)) {
            return filled($value[0] ?? null) ? (string) $value[0] : null;
        }

        return filled($value) ? (string) $value : null;
    }
}
