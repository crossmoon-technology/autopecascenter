<?php

namespace Tests\Feature\Console;

use App\Jobs\ScrapeCatalog;
use App\Models\Catalog;
use App\Services\CatalogScraping\Scrapers\C123CatalogScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScrapeCatalogsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scrapers' => [
            ['name' => 'Willtec', 'slug' => 'willtec', 'url' => 'https://c123.com.br/willtec', 'class' => C123CatalogScraper::class],
            ['name' => 'Bel-Ar', 'slug' => 'bel-ar', 'url' => 'https://c123.com.br/bel-ar', 'class' => C123CatalogScraper::class],
        ]]);
    }

    public function test_dispatches_the_job_for_every_eligible_catalog(): void
    {
        Queue::fake();

        $willtecCatalog = Catalog::factory()->create(['scraper_slug' => 'willtec']);
        $belArCatalog = Catalog::factory()->create(['scraper_slug' => 'bel-ar']);

        $this->artisan('catalogs:scrape')->assertSuccessful();

        Queue::assertPushed(ScrapeCatalog::class, fn ($job) => $job->catalog->is($willtecCatalog));
        Queue::assertPushed(ScrapeCatalog::class, fn ($job) => $job->catalog->is($belArCatalog));
    }

    public function test_restricts_to_the_given_slugs_option(): void
    {
        Queue::fake();

        $willtecCatalog = Catalog::factory()->create(['scraper_slug' => 'willtec']);
        $belArCatalog = Catalog::factory()->create(['scraper_slug' => 'bel-ar']);

        $this->artisan('catalogs:scrape', ['--slug' => [$willtecCatalog->slug]])->assertSuccessful();

        Queue::assertPushed(ScrapeCatalog::class, fn ($job) => $job->catalog->is($willtecCatalog));
        Queue::assertNotPushed(ScrapeCatalog::class, fn ($job) => $job->catalog->is($belArCatalog));
    }

    public function test_ignores_catalogs_without_a_scraper_slug(): void
    {
        Queue::fake();

        Catalog::factory()->create(['scraper_slug' => null]);

        $this->artisan('catalogs:scrape')->assertSuccessful();

        Queue::assertNotPushed(ScrapeCatalog::class);
    }

    public function test_ignores_catalogs_whose_scraper_slug_is_not_in_config(): void
    {
        Queue::fake();

        Catalog::factory()->create(['scraper_slug' => 'nao-configurado']);

        $this->artisan('catalogs:scrape')->assertSuccessful();

        Queue::assertNotPushed(ScrapeCatalog::class);
    }

    public function test_does_nothing_when_there_are_no_catalogs_at_all(): void
    {
        Queue::fake();

        $this->assertSame(0, Catalog::query()->count());

        $this->artisan('catalogs:scrape')->assertSuccessful();

        Queue::assertNotPushed(ScrapeCatalog::class);
    }
}
