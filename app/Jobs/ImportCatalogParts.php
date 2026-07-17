<?php

namespace App\Jobs;

use App\Models\Catalog;
use App\Models\Part;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportCatalogParts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public readonly Catalog $catalog,
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->catalog->file)) {
            return;
        }

        $imported_count = 0;

        foreach (preg_split('/\r\n|\r|\n/', $disk->get($this->catalog->file)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);

            if (! is_array($data) || blank($data['codigo'] ?? null)) {
                continue;
            }

            Part::query()->updateOrCreate(
                [
                    'catalog_id' => $this->catalog->id,
                    'codigo' => $data['codigo'],
                ],
                [
                    'descricao' => $data['descricao'] ?? null,
                    'tipo' => $data['tipo'] ?? null,
                    'posicao' => $data['posicao'] ?? null,
                    'categoria' => $data['categoria'] ?? null,
                    'conversoes' => $data['conversoes'] ?? null,
                ]
            );

            $imported_count++;
        }

        if ($imported_count > 0) {
            $this->catalog->update(['is_active' => true]);
        }
    }
}
