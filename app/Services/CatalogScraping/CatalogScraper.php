<?php

namespace App\Services\CatalogScraping;

interface CatalogScraper
{
    /**
     * Returns null when the source's data hasn't changed since $known_source_version
     * (no products fetched in that case — the caller should skip the reimport).
     */
    public function scrape(?string $known_source_version): ?ScrapedCatalog;
}
