<?php

namespace Tests\Feature\Services\CatalogScraping;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\CatalogScraperRegistry;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\CatalogScraping\Scrapers\C123CatalogScraper;
use Tests\TestCase;

class CatalogScraperRegistryTest extends TestCase
{
    private function fakeConfig(): void
    {
        config(['scrapers' => [
            ['name' => 'Willtec', 'slug' => 'willtec', 'url' => 'https://c123.com.br/willtec', 'class' => C123CatalogScraper::class],
            ['name' => 'Bel-Ar', 'slug' => 'bel-ar', 'url' => 'https://c123.com.br/bel-ar', 'class' => C123CatalogScraper::class],
        ]]);
    }

    public function test_resolves_a_configured_slug_to_its_scraper_instance(): void
    {
        $this->fakeConfig();

        $scraper = (new CatalogScraperRegistry)->for('willtec');

        $this->assertInstanceOf(C123CatalogScraper::class, $scraper);
    }

    public function test_returns_null_for_a_null_slug(): void
    {
        $this->fakeConfig();

        $this->assertNull((new CatalogScraperRegistry)->for(null));
    }

    public function test_returns_null_for_a_slug_not_present_in_config(): void
    {
        $this->fakeConfig();

        $this->assertNull((new CatalogScraperRegistry)->for('nao-configurado'));
    }

    public function test_options_lists_slug_to_name_pairs_from_config(): void
    {
        $this->fakeConfig();

        $this->assertSame([
            'willtec' => 'Willtec',
            'bel-ar' => 'Bel-Ar',
        ], (new CatalogScraperRegistry)->options());
    }

    /**
     * Different manufacturers/platforms may need entirely different extraction
     * logic — the registry just instantiates whatever class config points at,
     * with the configured url, so a non-C123 scraper works the same way.
     */
    public function test_instantiates_an_arbitrary_configured_scraper_class_with_the_configured_url(): void
    {
        config(['scrapers' => [
            ['name' => 'Exemplo', 'slug' => 'exemplo', 'url' => 'https://example.com', 'class' => FakeScraperForRegistryTest::class],
        ]]);

        $scraper = (new CatalogScraperRegistry)->for('exemplo');

        $this->assertInstanceOf(FakeScraperForRegistryTest::class, $scraper);
        $this->assertSame('https://example.com', $scraper->baseUrl);
    }
}

class FakeScraperForRegistryTest implements CatalogScraper
{
    public function __construct(public readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        return null;
    }
}
