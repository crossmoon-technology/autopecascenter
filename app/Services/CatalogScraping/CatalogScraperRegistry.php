<?php

namespace App\Services\CatalogScraping;

class CatalogScraperRegistry
{
    /**
     * Resolves a Catalog's scraper_slug to a configured scraper instance, per
     * config/scrapers.php. The class listed there must accept a single
     * `string $baseUrl` constructor argument.
     */
    public function for(?string $scraper_slug): ?CatalogScraper
    {
        if ($scraper_slug === null) {
            return null;
        }

        $entry = collect(config('scrapers', []))->firstWhere('slug', $scraper_slug);

        if ($entry === null) {
            return null;
        }

        return new $entry['class']($entry['url']);
    }

    /**
     * @return array<string, string> slug => name, for populating the Select on CatalogForm
     */
    public function options(): array
    {
        return collect(config('scrapers', []))->pluck('name', 'slug')->all();
    }
}
