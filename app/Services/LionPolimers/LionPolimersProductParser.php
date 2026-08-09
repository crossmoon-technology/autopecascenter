<?php

namespace App\Services\LionPolimers;

use Illuminate\Support\Collection;

/**
 * Parses lionpolimers.com's own WooCommerce Store REST API
 * (`/wp-json/wc/store/v1/products`) — a genuinely public, unauthenticated
 * JSON API confirmed live (no session/key needed), same tier of "easy" as
 * Biagio Turbos' own API elsewhere in this app, just a standard WooCommerce
 * plugin instead of a custom backend.
 *
 * Confirmed live: the store's own custom product attributes use confusing
 * labels relative to this app's own field names — "Aplicação" is actually the
 * PART TYPE/description (e.g. "MANGUEIRA SUPERIOR DO RADIADOR"), while
 * "Veículo (Ano)" is the actual vehicle application this app calls
 * `aplicacao`, and "Código Original" is the cross-reference/OEM code list
 * (`conversoes`) — a product's own top-level `name` field IS its código
 * (confirmed identical to the "Código Lion" attribute in every sample
 * checked, but `name` is guaranteed present at the top level regardless of
 * attribute configuration, so it's used directly instead). Multi-value
 * attributes (multiple OEM codes, multiple vehicle years) are a single term
 * whose own name embeds literal `\n` characters between values — not
 * multiple terms — confirmed live across several categories (Linha Leve,
 * Linha Pesada, Linha Agrícola).
 */
final class LionPolimersProductParser
{
    /**
     * @return array<int, array{codigo: string, descricao: string, grupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_url: ?string}>
     */
    public static function extractListing(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        return collect($data)
            ->map(fn (mixed $item) => self::mapItem($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{codigo: string, descricao: string, grupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_url: ?string}|null
     */
    private static function mapItem(mixed $item): ?array
    {
        if (! is_array($item) || blank($item['name'] ?? null)) {
            return null;
        }

        $codigo = (string) $item['name'];
        $attributes = self::attributeMap(is_array($item['attributes'] ?? null) ? $item['attributes'] : []);

        return [
            'codigo' => $codigo,
            'descricao' => filled($attributes['Aplicação'] ?? null) ? trim($attributes['Aplicação']) : $codigo,
            'grupo' => filled($attributes['Montadora'] ?? null) ? trim($attributes['Montadora']) : null,
            'aplicacao' => self::buildAplicacao($attributes),
            'conversoes' => self::extractConversoes($attributes['Código Original'] ?? null, $codigo),
            'imagem_url' => filled($item['images'][0]['src'] ?? null) ? (string) $item['images'][0]['src'] : null,
        ];
    }

    /**
     * @param  array<int, mixed>  $attributes
     * @return array<string, string>
     */
    private static function attributeMap(array $attributes): array
    {
        return collect($attributes)
            ->filter(fn (mixed $attribute): bool => is_array($attribute) && filled($attribute['name'] ?? null))
            ->mapWithKeys(function (array $attribute): array {
                $value = $attribute['terms'][0]['name'] ?? null;

                return [(string) $attribute['name'] => filled($value) ? (string) $value : ''];
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private static function buildAplicacao(array $attributes): ?string
    {
        $montadora = trim($attributes['Montadora'] ?? '');
        $veiculos = filled($attributes['Veículo (Ano)'] ?? null) ? self::splitLines($attributes['Veículo (Ano)']) : collect();

        $lines = $veiculos
            ->map(fn (string $veiculo): string => trim("{$montadora} {$veiculo}"))
            ->filter()
            ->unique()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode(', ') : null;
    }

    /**
     * @return array<int, string>|null
     */
    private static function extractConversoes(?string $codigoOriginal, string $codigo): ?array
    {
        if (blank($codigoOriginal)) {
            return null;
        }

        $normalizedCodigo = self::normalizeToken($codigo);

        $codes = self::splitLines($codigoOriginal)
            ->map(fn (string $code) => self::normalizeToken($code))
            ->filter(fn (string $code): bool => $code !== '' && $code !== $normalizedCodigo)
            ->unique()
            ->values();

        return $codes->isNotEmpty() ? $codes->all() : null;
    }

    /**
     * @return Collection<int, string>
     */
    private static function splitLines(string $value): Collection
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();
    }

    private static function normalizeToken(string $value): string
    {
        return trim($value);
    }
}
