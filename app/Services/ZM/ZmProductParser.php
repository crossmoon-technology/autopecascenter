<?php

namespace App\Services\ZM;

/**
 * Parses one `cod_tipo_produto` group from extranet.zm.com.br's
 * `catalogo-busca-produtos` JSON API response — ZM's own public "Catálogo
 * On-Line" (www.zm.com.br/catalogo, an AngularJS SPA) calls this same
 * endpoint itself. Each group returns EVERY product of that type in one
 * shot (confirmed live up to 2683 products in a single response) alongside
 * up to 9 dynamically-labelled `des_campoN`/`val_campoN` field pairs whose
 * MEANING varies per product type — e.g. "Relés de Partida" labels
 * campo1/2/3 as Aplicação/Número Original/Voltagem, while "Parafusos de
 * Roda" labels them Aplicação/Dimensões/Acabamento, and "Fixadores -
 * Parafusos" has no Aplicação field at all — so which val_campoN (if any)
 * holds the cross-reference/OEM codes has to be resolved from the labels
 * themselves every time, never a fixed position.
 *
 * Confirmed live: both the montadora/application field and the
 * cross-reference field can carry MULTIPLE values, newline-separated
 * (`"CASE\nCUMMINS\n..."` / `"0.333.006.026\n0.333.AD5.141\n..."`).
 */
final class ZmProductParser
{
    private const string APLICACAO_LABEL = 'Aplicação';

    /**
     * Both variants confirmed live across different product types — same
     * meaning (OEM/cross-reference codes), different wording.
     */
    private const array CROSS_REFERENCE_LABELS = ['Número Original', 'Códigos Originais'];

    /**
     * @param  array<string, mixed>  $tipoResponse  one entry of the `message` array from catalogo-busca-produtos
     * @return array<int, array{codigo: string, descricao: string, conversoes: ?array<int, string>, fabricante: ?string, grupo: ?string, imagem_url: ?string}>
     */
    public static function extractProducts(array $tipoResponse): array
    {
        $aplicacaoField = self::findField($tipoResponse, self::APLICACAO_LABEL);
        $crossReferenceField = self::findField($tipoResponse, self::CROSS_REFERENCE_LABELS);
        $grupo = filled($tipoResponse['des_tipo'] ?? null) ? $tipoResponse['des_tipo'] : null;

        return collect($tipoResponse['produtos'] ?? [])
            ->filter(fn (array $product) => filled($product['des_produto_lista_galeria'] ?? null))
            ->map(function (array $product) use ($aplicacaoField, $crossReferenceField, $grupo) {
                $aplicacao = self::extractMultiValue($product, $aplicacaoField);

                return [
                    'codigo' => (string) $product['des_produto_lista_galeria'],
                    'descricao' => self::normalizeText((string) ($product['des_produto_lista'] ?? $product['des_produto'] ?? '')),
                    'conversoes' => self::extractMultiValue($product, $crossReferenceField),
                    'fabricante' => $aplicacao !== null ? implode(', ', $aplicacao) : null,
                    'grupo' => $grupo,
                    'imagem_url' => filled($product['des_caminho_imagem'] ?? null) ? $product['des_caminho_imagem'] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  string|array<int, string>  $labels
     */
    private static function findField(array $tipoResponse, string|array $labels): ?string
    {
        $labels = (array) $labels;

        for ($i = 1; $i <= 9; $i++) {
            if (in_array($tipoResponse["des_campo{$i}"] ?? null, $labels, true)) {
                return "val_campo{$i}";
            }
        }

        return null;
    }

    /**
     * @return array<int, string>|null
     */
    private static function extractMultiValue(array $product, ?string $field): ?array
    {
        if ($field === null || blank($product[$field] ?? null)) {
            return null;
        }

        $values = collect(explode("\n", (string) $product[$field]))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values();

        return $values->isNotEmpty() ? $values->all() : null;
    }

    private static function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
