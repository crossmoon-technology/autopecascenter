<?php

namespace App\Jobs;

use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use App\Services\CatalogImport\UpsertPartsFromJsonl;
use App\Services\PartEquivalence\RebuildPartEquivalences;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports a one-off `.jsonl` upload into a catalog WITHOUT ever writing to
 * catalog.file/update_file — unlike ImportCatalogParts/ImportCatalogPartsUpdate,
 * this deliberately doesn't couple the catalog to the uploaded file (the
 * point: seeding a catalog from data already scraped elsewhere, e.g. moving
 * an already-scraped catalog from this environment into production without
 * re-hitting the source site there — see the "Importar (sem vincular
 * arquivo)" action in CatalogsTable). Works regardless of scraper_slug,
 * unlike every other manual import path in this app.
 */
class ImportCatalogPartsFromUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public readonly Catalog $catalog,
        public readonly string $path,
    ) {}

    public function handle(UpsertPartsFromJsonl $upsert): void
    {
        $disk = Storage::disk('local');

        try {
            if (! $disk->exists($this->path)) {
                return;
            }

            $result = $upsert->execute($this->catalog, $disk->get($this->path));

            if ($result['imported_count'] > 0) {
                $this->catalog->update(['is_active' => true]);
                $this->catalog->forceFill(['import_status' => ImportStatus::Imported])->save();

                collect($result['imported_part_ids'])->chunk(500)->each(
                    fn ($chunk) => app(RebuildPartEquivalences::class)->forParts(Part::query()->whereIn('id', $chunk)->get())
                );
            } elseif ($this->catalog->import_status !== ImportStatus::Imported) {
                // Um upload vazio/sem linhas válidas não deve regredir um catálogo que
                // já estava Imported (ex: seed de cima de peças que já existiam) — só
                // derruba pra NotImported quando não havia nada importado antes.
                $this->catalog->forceFill(['import_status' => ImportStatus::NotImported])->save();
            }
        } catch (Throwable $exception) {
            $this->catalog->forceFill(['import_status' => ImportStatus::NotImported])->save();

            throw $exception;
        } finally {
            // Transitório de propósito — nunca vira catalogs.file/update_file, então
            // não há razão pra manter esse arquivo depois de processado.
            $disk->delete($this->path);
        }
    }
}
