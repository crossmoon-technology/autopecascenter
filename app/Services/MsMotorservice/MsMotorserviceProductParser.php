<?php

namespace App\Services\MsMotorservice;

use App\Services\Ideia2001\Ideia2001ProductFields;

/**
 * Parses the embedded JSON blobs from catweb.ms-motorservice.com.br (MS
 * Motorservice — Kolbenschmidt/KS, BF, and other sub-brands), a "cw"/Ideia2001
 * platform page. The listing page (resultado.php?cw_pgAtual=N) embeds a
 * `__CW_DATA_LISTA_RESULTADO__` script with just enough per-product data to
 * enumerate the catalog and detect changes (CodigoProduto/NumeroProduto); the
 * full description, grupo/subgrupo, vehicle applications and — critically —
 * cross-reference/OEM codes (`ReferenciasCruzada`) only appear on each
 * product's own detail page (`__CW_DATA_DETALHES_PRODUTO__`), fetched via
 * detalhes.php?cw_produtoAtivo=CodigoProduto<!2!>{id}.
 *
 * NOT every storefront on this platform is configured this sparingly, though
 * — confirmed live: IKS's own `__CW_DATA_LISTA_RESULTADO__` embeds the exact
 * same full shape (FabricantesAplicacao/ReferenciasCruzada included) directly
 * in the listing, with only grupo/subgrupo missing — see extractFullListing(),
 * which needs no per-product detail fetch at all for that case.
 */
final class MsMotorserviceProductParser
{
    /**
     * @return array<int, array{codigo_produto: int, codigo: string}>
     */
    public static function extractListing(string $html): array
    {
        $data = self::extractJson($html, '__CW_DATA_LISTA_RESULTADO__');

        return collect($data)
            ->map(fn (array $item): array => [
                'codigo_produto' => (int) ($item['CodigoProduto'] ?? 0),
                'codigo' => (string) ($item['NumeroProduto'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['codigo_produto'] > 0 && $item['codigo'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{codigo: string, descricao: string, fabricante: ?string, grupo: ?string, subgrupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_arquivo: ?string}>
     */
    public static function extractFullListing(string $html): array
    {
        $data = self::extractJson($html, '__CW_DATA_LISTA_RESULTADO__');

        return collect($data)
            ->map(fn (array $item) => self::mapItem($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The page shows either "N produto encontrado" or "N produtos" — the
     * plural counter is the one populated whenever there's more than one
     * result, which is always true for a full-catalog scrape.
     */
    public static function extractTotal(string $html): ?int
    {
        if (preg_match_all('/id="cw-total-resultado(?:-plural)?">([\d.]*)</', $html, $matches) === false) {
            return null;
        }

        $value = collect($matches[1])->first(fn (string $v): bool => $v !== '');

        return $value !== null ? (int) str_replace('.', '', $value) : null;
    }

    /**
     * @return array{codigo: string, descricao: string, fabricante: ?string, grupo: ?string, subgrupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_arquivo: ?string}|null
     */
    public static function extractDetail(string $html): ?array
    {
        $data = self::extractJson($html, '__CW_DATA_DETALHES_PRODUTO__');

        return self::mapItem($data[0] ?? null);
    }

    /**
     * @return array{codigo: string, descricao: string, fabricante: ?string, grupo: ?string, subgrupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_arquivo: ?string}|null
     */
    private static function mapItem(mixed $item): ?array
    {
        if (! is_array($item) || blank($item['NumeroProduto'] ?? null)) {
            return null;
        }

        $codigo = (string) $item['NumeroProduto'];

        return [
            'codigo' => $codigo,
            'descricao' => (string) ($item['DescricaoProduto'] ?? $codigo),
            'fabricante' => filled($item['DescricaoFabricante'] ?? null) ? $item['DescricaoFabricante'] : null,
            'grupo' => filled($item['DescricaoGrupoProduto'] ?? null) ? $item['DescricaoGrupoProduto'] : null,
            'subgrupo' => filled($item['DescricaoSubGrupoProduto'] ?? null) ? $item['DescricaoSubGrupoProduto'] : null,
            'aplicacao' => Ideia2001ProductFields::summarizeAplicacoes($item['FabricantesAplicacao'] ?? []),
            'conversoes' => Ideia2001ProductFields::extractConversoes($item['ReferenciasCruzada'] ?? [], $codigo),
            'imagem_arquivo' => filled($item['ArquivoFotoProduto'] ?? null) ? $item['ArquivoFotoProduto'] : null,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function extractJson(string $html, string $scriptId): array
    {
        if (! preg_match('/<script id="'.preg_quote($scriptId, '/').'" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            return [];
        }

        $decoded = json_decode($matches[1], true);

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    }
}
