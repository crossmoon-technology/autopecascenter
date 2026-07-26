<?php

namespace App\Jobs;

use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * "Subir atualizações" (ver App\Filament\Resources\Catalogs\Tables\CatalogsTable) — pra
 * um catálogo JÁ importado, anexa um arquivo extra no mesmo formato jsonl do import
 * original, mas só CRIA peças com código que ainda não existe nesse catálogo; um código
 * repetido é ignorado (mantém a peça já cadastrada como está). Diferente de
 * App\Jobs\ImportCatalogParts, que faz updateOrCreate (sobrescreve) — esse aqui é
 * estritamente aditivo, de propósito, já que "atualização" aqui significa "completar o
 * catálogo com peças novas", não corrigir as que já foram importadas.
 */
class ImportCatalogPartsUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public readonly Catalog $catalog,
        public readonly string $file,
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('local');

        $this->catalog->forceFill(['import_status' => ImportStatus::Importing])->save();

        $file_exists = $disk->exists($this->file);
        $previous_update_file = $this->catalog->update_file;

        try {
            if ($file_exists) {
                foreach (preg_split('/\r\n|\r|\n/', $disk->get($this->file)) as $line) {
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    $data = json_decode($line, true);

                    if (! is_array($data) || blank($data['codigo'] ?? null)) {
                        continue;
                    }

                    $atributos = collect($data)->except(['codigo', 'conversoes'])->filter(fn ($value) => ! is_null($value));

                    // firstOrCreate: se o código já existe nesse catálogo, ignora — só
                    // cria quando é realmente novo.
                    Part::query()->firstOrCreate(
                        [
                            'catalog_id' => $this->catalog->id,
                            'codigo' => $data['codigo'],
                        ],
                        [
                            'conversoes' => $data['conversoes'] ?? null,
                            'atributos' => $atributos->isNotEmpty() ? $atributos->all() : null,
                        ]
                    );
                }
            }
        } finally {
            // O catálogo já estava Imported antes dessa atualização — sucesso ou falha,
            // volta pro mesmo estado (nunca NotImported, que destravaria o campo de
            // arquivo original e escondería as peças já existentes).
            //
            // update_file (fora do Fillable — só esse job escreve aqui) guarda o último
            // arquivo de atualização processado, substituindo o anterior: só mantém um
            // por vez, pra não acumular arquivo antigo no disco à toa.
            $this->catalog->forceFill([
                'import_status' => ImportStatus::Imported,
                ...($file_exists ? ['update_file' => $this->file] : []),
            ])->save();

            if ($file_exists && $previous_update_file && $previous_update_file !== $this->file) {
                $disk->delete($previous_update_file);
            }
        }
    }
}
