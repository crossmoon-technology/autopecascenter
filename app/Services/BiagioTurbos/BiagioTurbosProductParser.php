<?php

namespace App\Services\BiagioTurbos;

/**
 * Parses catalogo.biagioturbos.com.br's own JSON API (`/api/turbos/{id}`) — a
 * React/Vite SPA (confirmed live: `<title>Catálogo Biagio Turbos</title>`,
 * bundle at `/assets/index-*.js`) whose axios `baseURL` is the relative `/api`
 * — a genuinely public, unauthenticated JSON API confirmed live (no session,
 * no API key), unlike Ajusa's TecAlliance platform earlier this session
 * (deferred) which genuinely required backend auth.
 *
 * There's no dedicated "list all turbos" endpoint on this API (confirmed by
 * grepping the whole bundle for every `.get("/turbos...")` call — only
 * `/turbos/{id}` exists) — see BiagioTurbosCatalogScraper for how turbo ids
 * are enumerated instead (sequential id scan, confirmed live: dense from 1 to
 * 509 with a handful of internal gaps for deleted records).
 *
 * `/turbos/{id}` already returns everything needed in one request — no
 * separate listing/detail split like ATE/Kaer elsewhere in this app:
 * `turbo.ligacoes[]` embeds every vehicle application (montadora/modelo/motor)
 * directly, and `tiposReferencias[].referencias[]` is the cross-reference
 * data (grouped by reference type — "O.E.M.", "P/N KKK", etc. — confirmed
 * live; the grouping label itself isn't needed, just the flat code list, same
 * as every other cross-reference field elsewhere in this app).
 */
final class BiagioTurbosProductParser
{
    /**
     * @return array{codigo: string, descricao: string, grupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_url: ?string}|null
     */
    public static function extractDetail(string $json, string $baseUrl): ?array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! is_array($turbo = $data['turbo'] ?? null) || blank($turbo['partNumber'] ?? null)) {
            return null;
        }

        $codigo = (string) $turbo['partNumber'];

        return [
            'codigo' => $codigo,
            'descricao' => filled($turbo['modelo'] ?? null) ? (string) $turbo['modelo'] : $codigo,
            'grupo' => filled($turbo['linha']['nome'] ?? null) ? (string) $turbo['linha']['nome'] : null,
            'aplicacao' => self::extractAplicacao($turbo['ligacoes'] ?? []),
            'conversoes' => self::extractConversoes($data['tiposReferencias'] ?? [], $codigo),
            'imagem_url' => self::extractImagem($turbo['arquivosLeves'] ?? [], $baseUrl),
        ];
    }

    /**
     * @param  array<int, mixed>  $ligacoes
     */
    private static function extractAplicacao(array $ligacoes): ?string
    {
        $lines = collect($ligacoes)
            ->map(function (mixed $ligacao): ?string {
                if (! is_array($ligacao)) {
                    return null;
                }

                $veiculo = $ligacao['veiculo'] ?? [];
                $montadora = (string) ($veiculo['montadora']['nome'] ?? '');
                $modelo = (string) ($veiculo['modelo'] ?? '');
                $motor = (string) ($ligacao['motor']['nome'] ?? '');

                $titulo = trim("{$montadora} {$modelo}");

                if ($titulo === '') {
                    return null;
                }

                return $motor !== '' ? "{$titulo} ({$motor})" : $titulo;
            })
            ->filter()
            ->unique()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode(', ') : null;
    }

    /**
     * @param  array<int, mixed>  $tiposReferencias
     * @return array<int, string>|null
     */
    private static function extractConversoes(array $tiposReferencias, string $codigo): ?array
    {
        $normalizedCodigo = self::normalizeToken($codigo);

        $codes = collect($tiposReferencias)
            ->flatMap(fn (mixed $tipo) => is_array($tipo) ? ($tipo['referencias'] ?? []) : [])
            ->map(fn (mixed $ref) => is_array($ref) ? (string) ($ref['referencia'] ?? '') : '')
            ->map(fn (string $ref) => self::normalizeToken($ref))
            ->filter(fn (string $ref): bool => $ref !== '' && $ref !== $normalizedCodigo)
            ->unique()
            ->values();

        return $codes->isNotEmpty() ? $codes->all() : null;
    }

    /**
     * @param  array<int, mixed>  $arquivosLeves
     */
    private static function extractImagem(array $arquivosLeves, string $baseUrl): ?string
    {
        $foto = collect($arquivosLeves)->first(fn (mixed $a): bool => is_array($a) && ($a['tipo'] ?? null) === 'foto' && filled($a['url'] ?? null));

        return $foto !== null ? $baseUrl.'/api'.$foto['url'] : null;
    }

    private static function normalizeToken(string $value): string
    {
        return trim($value);
    }
}
