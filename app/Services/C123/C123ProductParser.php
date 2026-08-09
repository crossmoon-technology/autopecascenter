<?php

namespace App\Services\C123;

/**
 * Parses the mPrd[...]=new fP();with(...){...} inline JS literal shared by
 * every storefront on the legacy ASP c123.com.br catalog engine (confirmed
 * identical across at least Willtec, Bel-Ar, and Fania). Used by the bulk
 * catalog scraper (Services\CatalogScraping\Scrapers\C123CatalogScraper,
 * FaniaCatalogScraper) — kept here instead of duplicated so this fragile
 * regex has exactly one home.
 *
 * Fields are extracted by name, not by a fixed positional sequence: a real
 * Willtec page (products under "Esportivo") showed entries with no `t=`
 * (image) at all and an extra trailing `ld=` field instead of the usual
 * `c;n;d;i;t;g;s;p` order — a strict positional regex silently matched zero
 * products on pages full of these, which a bulk scraper misreads as "no more
 * pages" and stops hundreds of pages short of the real total.
 *
 * Some storefronts on this engine (confirmed live: Fania, Taranto) also embed
 * cross-reference/OEM codes as a separate `mRef[N]=new fR();with(...){f=[...];n=[...];}`
 * literal per product, keyed by the SAME `N` as that product's own
 * codigoInterno (`c=` in its mPrd block) — confirmed live those indices line
 * up 1:1. Willtec/Bel-Ar/Cipec's bulk pagination batches never carried mRef
 * at all, so extractCrossReferences() is a no-op (empty array) for those.
 */
final class C123ProductParser
{
    /**
     * @return array<int, array{codigoInterno: string, codigo: string, descricao: string, imagem: string, grupo: string, subgrupo: string}>
     */
    public static function extractProducts(string $body): array
    {
        preg_match_all(
            '/mPrd\[\d+\]=new fP\(\);with\(mPrd\[\d+\]\)\{(.*?)\}/',
            $body,
            $blocks,
            PREG_SET_ORDER
        );

        return array_values(array_filter(array_map(
            fn (array $block) => self::parseBlock($block[1]),
            $blocks
        )));
    }

    /**
     * @return array{codigoInterno: string, codigo: string, descricao: string, imagem: string, grupo: string, subgrupo: string}|null
     */
    private static function parseBlock(string $fieldsRaw): ?array
    {
        // c= é o único campo numérico sem aspas (é o id interno usado como chave
        // de mApl/mRef) — os demais que usamos vêm sempre entre aspas simples.
        if (! preg_match('/(?:^|;)c=(\d+);/', $fieldsRaw, $idMatch)) {
            return null;
        }

        preg_match_all("/(\w+)='(.*?)';/", $fieldsRaw, $pairs, PREG_SET_ORDER);

        $fields = [];

        foreach ($pairs as $pair) {
            $fields[$pair[1]] = $pair[2];
        }

        return [
            'codigoInterno' => $idMatch[1],
            'codigo' => $fields['n'] ?? '',
            'descricao' => $fields['d'] ?? '',
            'imagem' => $fields['t'] ?? '',
            'grupo' => $fields['g'] ?? '',
            'subgrupo' => $fields['s'] ?? '',
        ];
    }

    /**
     * @return array<string, array<int, string>> codigoInterno => raw cross-reference codes (untrimmed brand info, not yet deduped/self-excluded — the caller has the product's own codigo to do that with)
     */
    public static function extractCrossReferences(string $body): array
    {
        preg_match_all('/mRef\[(\d+)\]=new fR\(\);with\(mRef\[\d+\]\)\{(.*?)\}/s', $body, $blocks, PREG_SET_ORDER);

        $result = [];

        foreach ($blocks as $block) {
            preg_match_all('/n\[\d+\]=\'(.*?)\';/', $block[2], $codes);

            $values = array_values(array_filter(array_map('trim', $codes[1])));

            if ($values !== []) {
                $result[$block[1]] = $values;
            }
        }

        return $result;
    }

    /**
     * Normalizes raw cross-reference codes into the shape stored on
     * Part.atributos.conversoes: uppercased, whitespace-stripped, deduped,
     * and with the product's own codigo excluded (mRef sometimes lists it
     * among its own cross-references) — shared by every scraper on this
     * engine that has cross-reference data (Fania, and any C123CatalogScraper
     * storefront whose mRef happens to be populated).
     *
     * @param  array<int, string>  $rawCodes
     * @return array<int, string>|null
     */
    public static function normalizeConversoes(array $rawCodes, string $codigo): ?array
    {
        $normalizedCodigo = self::normalizeToken($codigo);

        $conversoes = collect($rawCodes)
            ->map(fn (string $value): string => self::normalizeToken($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->reject(fn (string $value): bool => $value === $normalizedCodigo)
            ->unique()
            ->values();

        return $conversoes->isNotEmpty() ? $conversoes->all() : null;
    }

    private static function normalizeToken(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)));
    }
}
