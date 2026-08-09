<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\BuscaNaRedeCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BuscaNaRedeCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function listingPage(array $items, int $total): string
    {
        $cards = collect($items)->map(fn (array $item) => <<<HTML
            card-content card-top">
                <a class="green-b-l" href="{$item['url']}" rel="page"></a>
                <table class="table table-hover"><tbody></tbody></table>
            </div>
            <div class="card-content">
                <a data-codigo="{$item['codigo']}" data-titulo="{$item['codigo']}" data-imagem=""></a>
            </div>
            HTML)->implode('');

        return "<div class=\"me-auto\"><b>{$total}</b> <span>produtos encontrados</span></div>{$cards}";
    }

    private function tabResponse(array $codes): string
    {
        return collect($codes)->map(fn (string $c) => "<span class=\"label\">{$c}</span>")->implode('');
    }

    public function test_scrapes_listing_and_conversoes_end_to_end(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'KTL1020', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/1/x'],
            ], total: 1), 200),
            'buscanarede.com.br/linmaxbrasil/produto/1/x/equivalences' => Http::response($this->tabResponse(['KTB255']), 200),
            'buscanarede.com.br/linmaxbrasil/produto/1/x/oem' => Http::response($this->tabResponse(['030198119A']), 200),
        ]);

        $result = (new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('KTL1020', $result->products[0]['codigo']);
        $this->assertSame(['KTB255', '030198119A'], $result->products[0]['conversoes']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_via_a_path_segment_not_a_query_param(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'X1', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/1/x'],
            ], total: 2), 200),
            'buscanarede.com.br/linmaxbrasil/produtos/2' => Http::response($this->listingPage([
                ['codigo' => 'X2', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/2/y'],
            ], total: 2), 200),
            'buscanarede.com.br/linmaxbrasil/produto/*' => Http::response('', 200),
        ]);

        $result = (new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil'))->scrape(null);

        $this->assertSame(['X1', 'X2'], array_column($result->products, 'codigo'));
    }

    public function test_skips_a_product_without_a_detail_url(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'X1', 'url' => ''],
            ], total: 1), 200),
        ]);

        $result = (new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
    }

    public function test_throws_before_fetching_any_detail_when_the_listing_falls_far_short_of_the_total(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'X1', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/1/x'],
            ], total: 100), 200),
            'buscanarede.com.br/linmaxbrasil/produtos/2' => Http::response($this->listingPage([], total: 100), 200),
        ]);

        try {
            (new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil'))->scrape(null);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
            // esperado
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/produto/1'));
    }

    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'X1', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/1/x'],
            ], total: 3), 200),
            'buscanarede.com.br/linmaxbrasil/produtos/2' => Http::response($this->listingPage([], total: 3), 200),
            'buscanarede.com.br/linmaxbrasil/produto/*' => Http::response('', 200),
        ]);

        $result = (new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_does_not_tolerate_any_shortfall_when_no_empty_page_was_ever_reached(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'X1', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/1/x'],
            ], total: 2), 200),
        ]);

        $scraper = new class('https://buscanarede.com.br/linmaxbrasil') extends BuscaNaRedeCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_throws_when_the_total_cannot_be_determined(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response('<html>sem dados</html>', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil'))->scrape(null);
    }

    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'X1', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/1/x'],
            ], total: 1), 200),
            'buscanarede.com.br/linmaxbrasil/produto/*' => Http::response('', 200),
        ]);

        $scraper = new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil');
        $first = $scraper->scrape(null);

        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::response($this->listingPage([
                ['codigo' => 'X1', 'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/1/x'],
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'buscanarede.com.br/linmaxbrasil/produtos' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['codigo' => 'X1', 'url' => ''],
                ], total: 1), 200),
        ]);

        $result = (new BuscaNaRedeCatalogScraper('https://buscanarede.com.br/linmaxbrasil'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
