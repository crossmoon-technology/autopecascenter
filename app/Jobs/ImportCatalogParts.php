<?php

namespace App\Jobs;

use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
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

    public function handle(): void
    {
        $disk = Storage::disk('local');

        if (blank($this->catalog->file) || ! $disk->exists($this->catalog->file)) {
            return;
        }

        $this->catalog->forceFill(['import_status' => ImportStatus::Importing])->save();

        try {
            $imported_count = 0;
            $imported_part_ids = [];

            foreach (preg_split('/\r\n|\r|\n/', $disk->get($this->catalog->file)) as $line) {
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
                $part = Part::query()->updateOrCreate(
                    [
                        'catalog_id' => $this->catalog->id,
                        'codigo' => Part::normalizeCode($data['codigo']),
                    ],
                    [
                        'conversoes' => Part::normalizeConversoes($data['conversoes'] ?? null),
                        'atributos' => $atributos->isNotEmpty() ? $atributos->all() : null,
                    ]
                );

                $imported_part_ids[] = $part->id;
                $imported_count++;
            }

            if ($imported_count > 0) {
                $this->catalog->update(['is_active' => true]);
                $this->catalog->forceFill(['import_status' => ImportStatus::Imported])->save();

                // Precisa ser feito no momento da importação, não em tempo de busca — ver
                // App\Services\PartEquivalence\RebuildPartEquivalences.
                collect($imported_part_ids)->chunk(500)->each(
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
