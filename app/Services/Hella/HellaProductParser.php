<?php

namespace App\Services\Hella;

use App\Services\Ideia2001\Ideia2001ProductFields;

/**
 * Parses catalogoexpresso.com.br/hella's React Server Components ("RSC")
 * flight payloads — the same "Ideia2001/CatalogoExpresso" product data model
 * as MS Motorservice (identical `FabricantesAplicacao`/`ReferenciasCruzada`
 * shapes, see Ideia2001ProductFields), but on a modern Next.js frontend
 * instead of MS Motorservice's legacy server-rendered "cw" pages. Requesting
 * any page/product URL with an `RSC: 1` header (no need for Next.js's own
 * `_rsc` cache-busting query param) returns this flight format directly
 * instead of a full HTML document — confirmed live, much lighter and, unlike
 * a full page load, has the query-cache data already dehydrated as plain
 * (singly-escaped) JSON embedded in the response text, not double-escaped
 * inside an outer HTML/JS string like a `self.__next_f.push(...)` chunk would
 * be. The flight format itself isn't valid JSON as a whole (numbered lines,
 * `$L`-prefixed React element references), so each data island is located by
 * its own marker and extracted via balanced-bracket scanning rather than
 * parsing the full response.
 */
final class HellaProductParser
{
    /**
     * @return array<int, array{codigo_produto: int, codigo: string}>
     */
    public static function extractListing(string $rscBody): array
    {
        $marker = '"produtos":';
        $pos = strpos($rscBody, $marker);
        $json = $pos !== false ? self::extractBalanced($rscBody, $pos + strlen($marker), '[', ']') : null;
        $data = $json !== null ? json_decode($json, true) : null;

        if (! is_array($data)) {
            return [];
        }

        return collect($data)
            ->map(fn (array $item): array => [
                'codigo_produto' => (int) ($item['CodigoProduto'] ?? 0),
                'codigo' => (string) ($item['NumeroProduto'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['codigo_produto'] > 0 && $item['codigo'] !== '')
            ->values()
            ->all();
    }

    public static function extractTotal(string $rscBody): ?int
    {
        return preg_match('/"totalResultado":(\d+)/', $rscBody, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * @return array{codigo: string, descricao: string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_arquivo: ?string}|null
     */
    public static function extractDetail(string $rscBody): ?array
    {
        $needle = '"data":{"CodigoProduto":';
        $pos = strpos($rscBody, $needle);
        $json = $pos !== false ? self::extractBalanced($rscBody, $pos + strlen('"data":'), '{', '}') : null;
        $item = $json !== null ? json_decode($json, true) : null;

        if (! is_array($item) || blank($item['NumeroProduto'] ?? null)) {
            return null;
        }

        $codigo = (string) $item['NumeroProduto'];

        return [
            'codigo' => $codigo,
            'descricao' => (string) ($item['DescricaoProduto'] ?? $codigo),
            'aplicacao' => Ideia2001ProductFields::summarizeAplicacoes($item['FabricantesAplicacao'] ?? []),
            'conversoes' => Ideia2001ProductFields::extractConversoes($item['ReferenciasCruzada'] ?? [], $codigo),
            'imagem_arquivo' => filled($item['ArquivoFotoProduto'] ?? null) ? $item['ArquivoFotoProduto'] : null,
        ];
    }

    private static function extractBalanced(string $text, int $start, string $open, string $close): ?string
    {
        if (($text[$start] ?? null) !== $open) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start, $len = strlen($text); $i < $len; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
