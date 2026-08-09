<?php

namespace App\Jobs;

use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Services\CatalogScraping\CatalogScraperRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scrapes a single catalog from its configured provider (config/scrapers.php,
 * resolved via CatalogScraperRegistry) and, if the source actually changed,
 * stores the fresh JSONL and dispatches ImportCatalogParts — a full refresh,
 * never ImportCatalogPartsUpdate (see App\Console\Commands\ScrapeCatalogs,
 * which dispatches this job for every eligible catalog on schedule; the
 * "Rodar scraper" table action dispatches it for one specific catalog on demand,
 * forcing import_status to Importing synchronously first so the button hides
 * immediately — this job is what has to resolve that status back out again).
 *
 * A full scrape can mean thousands of sequential HTTP requests (C123's
 * resaj.asp pagination, Kaer's and MS Motorservice's one-request-per-part
 * detail fetch) — a real Willtec catalog turned out to have 13385 parts
 * (~450 resaj.asp pages), and MS Motorservice's 4342 parts each need their own
 * detail-page fetch on top of the 434-page listing (confirmed live timing:
 * ~90 minutes end to end) — both already blew past smaller timeouts in
 * practice, so this job needs a generous allowance and shouldn't burn another
 * several minutes retrying an attempt that just timed out.
 */
class ScrapeCatalog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 7200;

    /**
     * Repeated real failures on MS Motorservice (site connection drops, worker
     * timeout kills) used to just sit permanently failed until someone
     * manually clicked "Rodar scraper" again — with $tries=1, Laravel never
     * retried on its own. MsMotorserviceCatalogScraper's checkpoint/resume
     * (see its own docblock) exists precisely to make a retry cheap and safe,
     * so it makes sense to let the queue actually use it automatically
     * instead of requiring a human to notice and re-trigger every time.
     */
    public int $tries = 6;

    /**
     * Tapers from 1 to 20 minutes between attempts — enough to ride out a
     * transient bad patch on the source site without hammering it right after
     * a failure. Laravel reuses the last value for any attempt beyond the
     * array's length.
     */
    public array $backoff = [60, 120, 300, 600, 1200];

    public function __construct(
        public readonly Catalog $catalog,
    ) {}

    public function handle(CatalogScraperRegistry $registry): void
    {
        $scraper = $registry->for($this->catalog->scraper_slug);

        if ($scraper === null) {
            $this->resetImportStatus();

            return;
        }

        try {
            $scraped = $scraper->scrape($this->catalog->source_version);
        } catch (Throwable $exception) {
            $this->resetImportStatus();

            throw $exception;
        }

        if ($scraped === null) {
            $this->resetImportStatus();

            return;
        }

        $disk = Storage::disk('local');
        $previous_file = $this->catalog->file;
        $path = 'catalogs/'.Str::uuid().'.jsonl';

        $disk->put($path, collect($scraped->products)
            ->map(fn (array $product) => json_encode($product, JSON_UNESCAPED_UNICODE))
            ->implode("\n"));

        $this->catalog->forceFill([
            'file' => $path,
            'source_version' => $scraped->source_version,
            'import_status' => ImportStatus::Importing,
            'extracted_at' => now(),
        ])->save();

        if ($previous_file && $previous_file !== $path) {
            $disk->delete($previous_file);
        }

        ImportCatalogParts::dispatch($this->catalog);
    }

    /**
     * A worker-enforced timeout kills the job's process from the outside —
     * the try/catch in handle() never runs in that case, only this hook does
     * (Laravel calls it once the job is considered permanently failed, whether
     * that's an in-process exception or a timeout kill). Without this, a timed
     * out scrape leaves import_status stuck on Importing forever.
     */
    public function failed(?Throwable $exception): void
    {
        $this->resetImportStatus();
    }

    /**
     * Called when there's nothing to import (source unchanged or the scrape
     * itself failed) — puts import_status back to what it should actually be,
     * instead of leaving it stuck on Importing (which the "Rodar scraper"
     * button's visibility, and the "Importar"/"Subir atualizações" actions and
     * "Ativo" toggle, all key off of).
     */
    private function resetImportStatus(): void
    {
        $this->catalog->forceFill([
            'import_status' => $this->catalog->parts()->exists() ? ImportStatus::Imported : ImportStatus::NotImported,
        ])->save();
    }
}
