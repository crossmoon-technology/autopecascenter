<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Services\PartEquivalence\RebuildPartEquivalences as RebuildPartEquivalencesService;
use Illuminate\Console\Command;

/**
 * Backfill único pra peças importadas ANTES dessa funcionalidade existir — o pareamento
 * normal já acontece sozinho a cada import/atualização de catálogo (ver
 * App\Jobs\ImportCatalogParts e App\Jobs\ImportCatalogPartsUpdate), não precisa rodar
 * isso de novo depois do backfill inicial a menos que as tabelas de índice sejam
 * truncadas manualmente.
 */
class RebuildPartEquivalences extends Command
{
    protected $signature = 'parts:rebuild-equivalences';

    protected $description = 'Recalcula o pareamento direto de equivalências para todas as peças (backfill)';

    public function handle(RebuildPartEquivalencesService $service): int
    {
        $total = Part::query()->count();
        $bar = $this->output->createProgressBar($total);

        Part::query()->chunkById(500, function ($parts) use ($service, $bar) {
            $service->forParts($parts);
            $bar->advance($parts->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info("Pareamento recalculado para {$total} peça(s).");

        return self::SUCCESS;
    }
}
