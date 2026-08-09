<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\LionPolimersCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LionPolimersCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function page(array $items, int $total): mixed
    {
        return Http::response(
            collect($items)->map(fn (array $item) => array_merge(['attributes' => [], 'images' => []], $item))->all(),
            200,
            ['X-WP-Total' => (string) $total]
        );
    }

    public function test_scrapes_listing_end_to_end(): void
    {
        Http::fake([
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=1*' => $this->page([
                ['name' => '8796'],
            ], 1),
        ]);

        $result = (new LionPolimersCatalogScraper('https://lionpolimers.com'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('8796', $result->products[0]['codigo']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_until_the_total_is_reached(): void
    {
        Http::fake([
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=1*' => $this->page([['name' => 'X1']], 2),
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=2*' => $this->page([['name' => 'X2']], 2),
        ]);

        $result = (new LionPolimersCatalogScraper('https://lionpolimers.com'))->scrape(null);

        $this->assertSame(['X1', 'X2'], array_column($result->products, 'codigo'));
    }

    public function test_throws_when_the_total_header_is_missing(): void
    {
        Http::fake([
            'lionpolimers.com/wp-json/wc/store/v1/products*' => Http::response([['name' => 'X1', 'attributes' => [], 'images' => []]], 200),
        ]);

        $scraper = new class('https://lionpolimers.com') extends LionPolimersCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_throws_before_fetching_further_when_the_listing_falls_far_short_of_the_total(): void
    {
        Http::fake([
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=1*' => $this->page([['name' => 'X1']], 100),
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=2*' => $this->page([], 100),
        ]);

        $this->expectException(RuntimeException::class);

        (new LionPolimersCatalogScraper('https://lionpolimers.com'))->scrape(null);
    }

    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=1*' => $this->page([['name' => 'X1']], 3),
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=2*' => $this->page([], 3),
        ]);

        $result = (new LionPolimersCatalogScraper('https://lionpolimers.com'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_skips_entirely_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=1*' => $this->page([['name' => 'X1']], 1),
        ]);

        $scraper = new LionPolimersCatalogScraper('https://lionpolimers.com');
        $first = $scraper->scrape(null);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'lionpolimers.com/wp-json/wc/store/v1/products*&page=1*' => Http::sequence()
                ->pushStatus(500)
                ->push(
                    collect([['name' => 'X1', 'attributes' => [], 'images' => []]])->all(),
                    200,
                    ['X-WP-Total' => '1']
                ),
        ]);

        $result = (new LionPolimersCatalogScraper('https://lionpolimers.com'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
