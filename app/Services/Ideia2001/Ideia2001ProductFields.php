<?php

namespace App\Services\Ideia2001;

/**
 * Shared field-shaping logic for the "Ideia2001/CatalogoExpresso" product data
 * model — confirmed identical across at least two different frontend
 * transports for this same backend: MS Motorservice's legacy "cw" engine
 * (JSON embedded in a <script> tag) and Hella's modern Next.js/RSC catalog
 * (JSON embedded in a React Server Components flight payload). Both expose
 * the exact same `FabricantesAplicacao`/`ReferenciasCruzada` shapes, so the
 * per-manufacturer parsers (MsMotorserviceProductParser, HellaProductParser)
 * only differ in HOW they locate/extract the raw JSON — not in what to do
 * with it once parsed.
 */
final class Ideia2001ProductFields
{
    /**
     * @param  array<int, array{DescricaoFabricante?: string, Aplicacoes?: array<int, array<string, mixed>>}>  $fabricantesAplicacao
     */
    public static function summarizeAplicacoes(array $fabricantesAplicacao): ?string
    {
        $summary = collect($fabricantesAplicacao)
            ->flatMap(function (array $group): array {
                $montadora = (string) ($group['DescricaoFabricante'] ?? '');

                return collect($group['Aplicacoes'] ?? [])
                    ->map(function (array $aplicacao) use ($montadora): string {
                        $modelo = trim($montadora.' '.($aplicacao['DescricaoAplicacao'] ?? ''));

                        $details = collect([
                            $aplicacao['ComplementoAplicacao3_1'] ?? null,
                            self::yearRange($aplicacao['ComplementoAplicacao3_2'] ?? null, $aplicacao['ComplementoAplicacao3_3'] ?? null),
                            $aplicacao['ComplementoAplicacao3_4'] ?? null,
                        ])->filter(fn ($v): bool => filled($v) && $v !== '-');

                        return $details->isNotEmpty() ? "{$modelo} ({$details->implode(', ')})" : $modelo;
                    })
                    ->all();
            })
            ->implode(', ');

        return $summary !== '' ? $summary : null;
    }

    private static function yearRange(mixed $start, mixed $end): ?string
    {
        $start = filled($start) && $start !== '-' ? (string) $start : null;
        $end = filled($end) && $end !== '-' ? (string) $end : null;

        return match (true) {
            $start !== null && $end !== null && $start !== $end => "{$start}-{$end}",
            $start !== null => $start,
            $end !== null => $end,
            default => null,
        };
    }

    /**
     * @param  array<int, array{NumerosProduto?: array<int, array{NumeroProduto?: string}>}>  $referenciasCruzada
     * @return array<int, string>|null
     */
    public static function extractConversoes(array $referenciasCruzada, string $codigo): ?array
    {
        $normalizedCodigo = self::normalizeToken($codigo);

        $conversoes = collect($referenciasCruzada)
            ->flatMap(fn (array $group) => collect($group['NumerosProduto'] ?? [])->pluck('NumeroProduto'))
            ->filter(fn ($value): bool => filled($value))
            ->map(fn (string $value): string => self::normalizeToken($value))
            ->reject(fn (string $token): bool => $token === $normalizedCodigo)
            ->unique()
            ->values();

        return $conversoes->isNotEmpty() ? $conversoes->all() : null;
    }

    private static function normalizeToken(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)));
    }
}
