<?php

namespace App\Services\PartSearch;

/**
 * Live per-query search against a manufacturer's own site — for manufacturers
 * where a full bulk scrape (see Services\CatalogScraping) isn't practical
 * (e.g. MTE-Thomson: no "list everything" mode, and each product's own detail
 * page is too slow to crawl ~4600 of in one job). Queried synchronously from
 * Filament\Pages\Buscas\CatalogDatabaseSearch alongside the normal database
 * search, not pre-imported.
 */
interface PartSearchProvider
{
    public function search(string $query, int $page = 1): PartSearchResultPage;
}
