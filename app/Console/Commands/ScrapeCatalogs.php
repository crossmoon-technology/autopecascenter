<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeCatalog;
use App\Models\Catalog;
use App\Services\CatalogScraping\CatalogScraperRegistry;
use Illuminate\Console\Command;

class ScrapeCatalogs extends Command
{
    protected $signature = 'catalogs:scrape {--slug=* : Restringe aos catálogos com esses slugs}';

    protected $description = 'Busca o catálogo completo dos fabricantes com scraper configurado (Bel-Ar, Willtec, ...) e reimporta quando houver mudança na fonte';

    public function handle(CatalogScraperRegistry $registry): void
    {
        $slugs = $this->option('slug');

        $catalogs = Catalog::query()
            ->whereNotNull('scraper_slug')
            ->when($slugs !== [], fn ($query) => $query->whereIn('slug', $slugs))
            ->get()
            ->filter(fn (Catalog $catalog) => $registry->for($catalog->scraper_slug) !== null);

        foreach ($catalogs as $catalog) {
            ScrapeCatalog::dispatch($catalog);
        }

        $this->info($catalogs->count().' catálogo(s) enviado(s) para scraping.');
    }
}
