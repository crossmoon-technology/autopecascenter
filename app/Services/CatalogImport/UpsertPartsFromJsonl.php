<?php

namespace App\Services\CatalogImport;

use App\Models\Catalog;
use App\Models\Part;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The line-by-line JSONL → Part upsert core shared by every catalog import
 * path (App\Jobs\ImportCatalogParts, reading from catalog.file; and
 * App\Jobs\ImportCatalogPartsFromUpload, reading from a transient upload
 * that never touches catalog.file) — kept here instead of duplicated so
 * this normalization/conflict-handling logic has exactly one home.
 */
final class UpsertPartsFromJsonl
{
    /**
     * @return array{imported_count: int, imported_part_ids: array<int, int>}
     */
    public function execute(Catalog $catalog, string $content): array
    {
        $imported_count = 0;
        $imported_part_ids = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);

            if (! is_array($data) || blank($data['codigo'] ?? null)) {
                continue;
            }

            $atributos = collect($data)->except(['codigo', 'conversoes'])->filter(fn ($value) => ! is_null($value));

            // Normaliza ANTES do updateOrCreate, não só no saving() do model — o
            // WHERE daqui precisa bater com o valor já normalizado de uma peça
            // existente, senão duas grafias do "mesmo" código (ex: "mg 19038" num
            // reimport vs "MG19038" já salvo) criam uma peça nova em vez de
            // atualizar, e colidem com o unique(catalog_id, codigo) no insert.
            try {
                $part = Part::query()->updateOrCreate(
                    [
                        'catalog_id' => $catalog->id,
                        'codigo' => Part::normalizeCode($data['codigo']),
                    ],
                    [
                        'conversoes' => Part::normalizeConversoes($data['conversoes'] ?? null),
                        'atributos' => $atributos->isNotEmpty() ? $atributos->all() : null,
                    ]
                );
            } catch (UniqueConstraintViolationException) {
                // O updateOrCreate acima já busca por (catalog_id, codigo) antes de
                // inserir, então isso só acontece quando existe uma peça SOFT-deletada
                // (fora do escopo padrão da query, por causa do SoftDeletes) ocupando a
                // mesma constraint única — ex: um catálogo que foi soft-deletado (ver
                // Catalog::booted(), que soft-deleta as peças junto) e depois restaurado,
                // sem que suas peças fossem restauradas junto. Não há como essa mesma
                // linha do arquivo já ter sido processada nesta mesma execução (o
                // primeiro updateOrCreate já teria resolvido via UPDATE), então é seguro
                // ignorar e seguir pras próximas linhas em vez de derrubar a importação
                // inteira por causa de uma peça órfã.
                continue;
            }

            $imported_part_ids[] = $part->id;
            $imported_count++;
        }

        return ['imported_count' => $imported_count, 'imported_part_ids' => $imported_part_ids];
    }
}
