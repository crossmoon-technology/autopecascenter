<?php

namespace App\Services\CatalogImport;

use App\Models\Catalog;
use App\Models\Part;

/**
 * Builds a `.jsonl` export of a catalog's CURRENT parts (from the database,
 * not catalogs.file) — the exact inverse mapping of UpsertPartsFromJsonl, so
 * the result round-trips cleanly back through it (see
 * App\Jobs\ImportCatalogPartsFromUpload, the decoupled import path this
 * pairs with).
 */
final class ExportCatalogPartsToJsonl
{
    public function execute(Catalog $catalog): string
    {
        return $catalog->parts()
            ->get()
            ->map(fn (Part $part) => json_encode(
                array_filter(
                    array_merge(
                        ['codigo' => $part->codigo, 'conversoes' => $part->conversoes],
                        $part->atributos ?? []
                    ),
                    fn ($value) => ! is_null($value)
                ),
                JSON_UNESCAPED_UNICODE
            ))
            ->implode("\n");
    }
}
