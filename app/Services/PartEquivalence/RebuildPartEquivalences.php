<?php

namespace App\Services\PartEquivalence;

use App\Models\Part;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pareamento DIRETO (não transitivo) entre peças, recalculado a cada import/atualização
 * de catálogo (ver App\Jobs\ImportCatalogParts e App\Jobs\ImportCatalogPartsUpdate) —
 * nunca em tempo de busca. Duas peças só viram par quando compartilham um código
 * (próprio ou de conversoes) ESPECIFICAMENTE entre elas; se A e B compartilham um
 * código e B e C compartilham outro (diferente), A e C não viram par só por tabela
 * (isso seria pareamento transitivo, decidido explicitamente que não é o que queremos
 * aqui — menos completo, mas sem risco de "contaminar" o grupo por um código coincidente).
 */
class RebuildPartEquivalences
{
    private const string TABLE_CODES = 'part_reference_codes';

    private const string TABLE_PAIRS = 'part_equivalences';

    private const int CHUNK_SIZE = 500;

    /**
     * Um token compartilhado por mais peças do que isso é tratado como ruído (código
     * genérico/placeholder de catálogo mal formatado), não como equivalência real — sem
     * esse teto, um único token assim geraria N*(N-1) pares e travaria o rebuild.
     */
    private const int MAX_PARTS_PER_TOKEN = 25;

    /**
     * @param  Collection<int, Part>  $parts
     */
    public function forParts(Collection $parts): void
    {
        $part_ids = $parts->pluck('id')->all();

        if (empty($part_ids)) {
            return;
        }

        DB::transaction(function () use ($parts, $part_ids) {
            $this->refreshReferenceCodes($parts, $part_ids);
            $this->refreshEquivalencePairs($part_ids);
        });
    }

    /**
     * @param  Collection<int, Part>  $parts
     * @param  array<int, int>  $part_ids
     */
    private function refreshReferenceCodes(Collection $parts, array $part_ids): void
    {
        DB::table(self::TABLE_CODES)->whereIn('part_id', $part_ids)->delete();

        $now = now();
        $rows = collect();

        foreach ($parts as $part) {
            foreach ($this->tokensFor($part) as $token) {
                $rows->push(['part_id' => $part->id, 'token' => $token, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $rows->unique(fn (array $row): string => $row['part_id'].'|'.$row['token'])
            ->chunk(self::CHUNK_SIZE)
            ->each(fn ($chunk) => DB::table(self::TABLE_CODES)->insertOrIgnore($chunk->all()));
    }

    /**
     * @param  array<int, int>  $part_ids
     */
    private function refreshEquivalencePairs(array $part_ids): void
    {
        // As peças afetadas podem ter perdido códigos que tinham antes (reimportação
        // com dado diferente) — os pares antigos envolvendo elas, dos dois lados, são
        // descartados e recalculados do zero a partir do índice de tokens já atualizado.
        DB::table(self::TABLE_PAIRS)
            ->whereIn('part_id', $part_ids)
            ->orWhereIn('equivalent_part_id', $part_ids)
            ->delete();

        $affectedTokens = DB::table(self::TABLE_CODES)
            ->whereIn('part_id', $part_ids)
            ->pluck('token')
            ->unique();

        $now = now();

        foreach ($affectedTokens->chunk(self::CHUNK_SIZE) as $tokenChunk) {
            $partIdsByToken = DB::table(self::TABLE_CODES)
                ->whereIn('token', $tokenChunk)
                ->get(['token', 'part_id'])
                ->groupBy('token');

            $pairs = collect();

            foreach ($partIdsByToken as $rowsSharingToken) {
                $idsSharingToken = $rowsSharingToken->pluck('part_id')->unique()->values();

                if ($idsSharingToken->count() > self::MAX_PARTS_PER_TOKEN) {
                    continue;
                }

                foreach ($idsSharingToken as $a) {
                    foreach ($idsSharingToken as $b) {
                        if ($a === $b) {
                            continue;
                        }

                        $pairs->push(['part_id' => $a, 'equivalent_part_id' => $b, 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
            }

            $pairs->unique(fn (array $pair): string => $pair['part_id'].'|'.$pair['equivalent_part_id'])
                ->chunk(self::CHUNK_SIZE)
                ->each(fn ($chunk) => DB::table(self::TABLE_PAIRS)->insertOrIgnore($chunk->all()));
        }
    }

    /**
     * @return array<int, string>
     */
    private function tokensFor(Part $part): array
    {
        $tokens = [$this->normalize($part->codigo)];

        foreach ($this->flatten($part->conversoes ?? []) as $codigo) {
            $tokens[] = $this->normalize($codigo);
        }

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => $token !== '')));
    }

    /**
     * @return array<int, string>
     */
    private function flatten(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->flatMap(fn ($item) => $this->flatten($item))->all();
        }

        return [(string) $value];
    }

    /**
     * Delega pra Part::normalizeCode() (maiúsculo, sem espaço) — mesma normalização
     * aplicada ao salvar uma Part (ver Part::booted()) e nas buscas (ver
     * App\Filament\Pages\Buscas\CatalogDatabaseSearch), garantindo que token gerado aqui
     * bate com o que a busca calcula pro termo digitado.
     */
    private function normalize(string $value): string
    {
        return Part::normalizeCode($value);
    }
}
