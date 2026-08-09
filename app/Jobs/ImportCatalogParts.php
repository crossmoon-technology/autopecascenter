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

class ImportCatalogParts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public readonly Catalog $catalog,
    ) {}

    public function handle(UpsertPartsFromJsonl $upsert): void
    {
        $disk = Storage::disk('local');

        if (blank($this->catalog->file) || ! $disk->exists($this->catalog->file)) {
            return;
        }

        $this->catalog->forceFill(['import_status' => ImportStatus::Importing])->save();

        try {
            $result = $upsert->execute($this->catalog, $disk->get($this->catalog->file));

            if ($result['imported_count'] > 0) {
                $this->catalog->update(['is_active' => true]);
                $this->catalog->forceFill(['import_status' => ImportStatus::Imported])->save();

                // Precisa ser feito no momento da importação, não em tempo de busca — ver
                // App\Services\PartEquivalence\RebuildPartEquivalences.
                collect($result['imported_part_ids'])->chunk(500)->each(
                    fn ($chunk) => app(RebuildPartEquivalences::class)->forParts(Part::query()->whereIn('id', $chunk)->get())
                );
            } else {
                $this->catalog->forceFill(['import_status' => ImportStatus::NotImported])->save();
            }
        } catch (Throwable $exception) {
            $this->catalog->forceFill(['import_status' => ImportStatus::NotImported])->save();

            throw $exception;
        }
    }
}
