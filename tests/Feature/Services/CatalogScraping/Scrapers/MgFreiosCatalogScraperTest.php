<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\MgFreiosCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MgFreiosCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function listingPage(array $products, int $total): string
    {
        $json = json_encode([
            'siteSearchResults' => $total,
            'listProducts' => $products,
        ]);

        return <<<HTML
            <html><body><script>dataLayer = [{$json}];</script></body></html>
            HTML;
    }

    private function detailPage(array $breadcrumbDetails = [], string $description = ''): string
    {
        $json = json_encode(['breadcrumbDetails' => $breadcrumbDetails]);

        return <<<HTML
            <html><body>
            <div class="board_htm description">{$description}</div>
            <script>dataLayer = [{$json}]</script>
            </body></html>
            HTML;
    }

    public function test_scrapes_listing_and_detail_pages_end_to_end(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*pg=1*' => Http::response($this->listingPage([
                ['reference' => 'MG-8002', 'nameProduct' => 'Jogo de lona de freio', 'brand' => 'Hyundai', 'urlImage' => 'https://loja.mgfreios.com.br/img.jpg', 'urlProduct' => 'https://loja.mgfreios.com.br/produto/mg-8002'],
            ], total: 1), 200),
            'loja.mgfreios.com.br/produto/mg-8002' => Http::response($this->detailPage([
                ['id' => 1491, 'name' => 'Lona de Freio', 'level' => 1],
                ['id' => 1467, 'name' => 'Lonas', 'level' => 2],
            ], 'MG-8002, Hyundai, Jogo de lona de freio, County, ORIG. 5830545A62'), 200),
        ]);

        $result = (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('MG-8002', $result->products[0]['codigo']);
        $this->assertSame('Jogo de lona de freio', $result->products[0]['descricao']);
        $this->assertSame('Hyundai', $result->products[0]['fabricante']);
        $this->assertSame('Lona de Freio', $result->products[0]['grupo']);
        $this->assertSame('Lonas', $result->products[0]['subgrupo']);
        $this->assertSame(['5830545A62'], $result->products[0]['conversoes']);
        $this->assertSame('https://loja.mgfreios.com.br/img.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_requests_the_listing_with_an_empty_search_term(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'X'],
            ], total: 1), 200),
        ]);

        (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);

        Http::assertSent(fn ($request) => $request->url() === 'https://loja.mgfreios.com.br/loja/busca.php?palavra_busca=&pg=1');
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*pg=1*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'Um'],
            ], total: 2), 200),
            'loja.mgfreios.com.br/loja/busca.php*pg=2*' => Http::response($this->listingPage([
                ['reference' => 'MG-2', 'nameProduct' => 'Dois'],
            ], total: 2), 200),
        ]);

        $result = (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);

        $this->assertCount(2, $result->products);
        $this->assertSame(['MG-1', 'MG-2'], array_column($result->products, 'codigo'));
    }

    public function test_skips_a_product_without_a_detail_url(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'Sem link'],
            ], total: 1), 200),
        ]);

        $result = (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);

        $this->assertNull($result->products[0]['grupo']);
        $this->assertNull($result->products[0]['conversoes']);
    }

    public function test_throws_before_fetching_any_detail_when_the_listing_falls_far_short_of_the_total(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*pg=1*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'X'],
            ], total: 100), 200),
            'loja.mgfreios.com.br/loja/busca.php*pg=2*' => Http::response($this->listingPage([], total: 100), 200),
        ]);

        try {
            (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
            // esperado
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'produto'));
    }

    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*pg=1*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'X'],
            ], total: 3), 200),
            'loja.mgfreios.com.br/loja/busca.php*pg=2*' => Http::response($this->listingPage([], total: 3), 200),
        ]);

        $result = (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_does_not_tolerate_any_shortfall_when_no_empty_page_was_ever_reached(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*pg=1*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'X'],
            ], total: 2), 200),
        ]);

        $scraper = new class('https://loja.mgfreios.com.br') extends MgFreiosCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_throws_when_the_total_cannot_be_determined(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*' => Http::response('<html><body>sem dados</body></html>', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);
    }

    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'X', 'urlProduct' => 'https://loja.mgfreios.com.br/produto/mg-1'],
            ], total: 1), 200),
            'loja.mgfreios.com.br/produto/mg-1' => Http::response($this->detailPage(), 200),
        ]);

        $scraper = new MgFreiosCatalogScraper('https://loja.mgfreios.com.br');
        $first = $scraper->scrape(null);

        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*' => Http::response($this->listingPage([
                ['reference' => 'MG-1', 'nameProduct' => 'X', 'urlProduct' => 'https://loja.mgfreios.com.br/produto/mg-1'],
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'loja.mgfreios.com.br/loja/busca.php*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['reference' => 'MG-1', 'nameProduct' => 'X'],
                ], total: 1), 200),
        ]);

        $result = (new MgFreiosCatalogScraper('https://loja.mgfreios.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
