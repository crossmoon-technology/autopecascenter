<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\MgPecasAutomotivasCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MgPecasAutomotivasCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function listingPage(array $items, int $total): string
    {
        $blocks = collect($items)->map(function (array $item) {
            $json = json_encode(array_merge(['@context' => 'https://schema.org/', '@type' => 'Product'], $item));

            return "<script type=\"application/ld+json\" data-component='structured-data.item'>{$json}</script>";
        })->implode('');

        return "LS.productsCount = {$total};{$blocks}";
    }

    private function detailPage(array $breadcrumbNames = [], string $userContentHtml = ''): string
    {
        $items = collect($breadcrumbNames)->map(fn (string $name, int $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name])->values()->all();
        $json = json_encode(['@context' => 'https://schema.org/', '@type' => 'WebPage', 'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => $items]]);

        return "<script type=\"application/ld+json\" data-component='structured-data.page'>{$json}</script>"
            ."<div class=\"user-content font-small mb-4\">{$userContentHtml}</div>";
    }

    public function test_scrapes_listing_and_detail_pages_end_to_end(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*page=1*' => Http::response($this->listingPage([
                ['name' => 'MG001', 'image' => 'https://cdn/mg001.webp', 'offers' => ['url' => 'https://mgpecasautomotivas.com.br/produtos/mg001/']],
            ], total: 1), 200),
            'mgpecasautomotivas.com.br/produtos/mg001/' => Http::response($this->detailPage(
                ['Início', 'MANG. FILTRO DE AR', 'FIAT', 'MG001'],
                '<p><span>MANGUEIRA FILTRO DE AR</span></p><p><span>Nº Original</span>: <span>46445723/46448515</span></p><p><span>Aplicação</span>: </p><p><span>PALIO 1.0 8V</span></p>'
            ), 200),
        ]);

        $result = (new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('MG001', $result->products[0]['codigo']);
        $this->assertSame('MANGUEIRA FILTRO DE AR', $result->products[0]['descricao']);
        $this->assertSame(['46445723', '46448515'], $result->products[0]['conversoes']);
        $this->assertSame('FIAT', $result->products[0]['fabricante']);
        $this->assertSame('MANG. FILTRO DE AR', $result->products[0]['grupo']);
        $this->assertSame('PALIO 1.0 8V', $result->products[0]['aplicacao']);
        $this->assertSame('https://cdn/mg001.webp', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*page=1*' => Http::response($this->listingPage([
                ['name' => 'MG001'],
            ], total: 2), 200),
            'mgpecasautomotivas.com.br/produtos*page=2*' => Http::response($this->listingPage([
                ['name' => 'MG002'],
            ], total: 2), 200),
        ]);

        $result = (new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br'))->scrape(null);

        $this->assertSame(['MG001', 'MG002'], array_column($result->products, 'codigo'));
    }

    public function test_skips_a_product_without_a_detail_url(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*' => Http::response($this->listingPage([
                ['name' => 'MG001'],
            ], total: 1), 200),
        ]);

        $result = (new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
        $this->assertSame('MG001', $result->products[0]['descricao']);
    }

    public function test_throws_before_fetching_any_detail_when_the_listing_falls_far_short_of_the_total(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*page=1*' => Http::response($this->listingPage([
                ['name' => 'MG001'],
            ], total: 100), 200),
            'mgpecasautomotivas.com.br/produtos*page=2*' => Http::response($this->listingPage([], total: 100), 200),
        ]);

        try {
            (new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br'))->scrape(null);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
            // esperado
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'produtos/mg'));
    }

    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*page=1*' => Http::response($this->listingPage([
                ['name' => 'MG001'],
            ], total: 3), 200),
            'mgpecasautomotivas.com.br/produtos*page=2*' => Http::response($this->listingPage([], total: 3), 200),
        ]);

        $result = (new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_does_not_tolerate_any_shortfall_when_no_empty_page_was_ever_reached(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*page=1*' => Http::response($this->listingPage([
                ['name' => 'MG001'],
            ], total: 2), 200),
        ]);

        $scraper = new class('https://mgpecasautomotivas.com.br') extends MgPecasAutomotivasCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_throws_when_the_total_cannot_be_determined(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*' => Http::response('<html>sem dados</html>', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br'))->scrape(null);
    }

    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*' => Http::response($this->listingPage([
                ['name' => 'MG001', 'offers' => ['url' => 'https://mgpecasautomotivas.com.br/produtos/mg001/']],
            ], total: 1), 200),
            'mgpecasautomotivas.com.br/produtos/mg001/' => Http::response($this->detailPage(), 200),
        ]);

        $scraper = new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br');
        $first = $scraper->scrape(null);

        Http::fake([
            'mgpecasautomotivas.com.br/produtos*' => Http::response($this->listingPage([
                ['name' => 'MG001', 'offers' => ['url' => 'https://mgpecasautomotivas.com.br/produtos/mg001/']],
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'mgpecasautomotivas.com.br/produtos*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['name' => 'MG001'],
                ], total: 1), 200),
        ]);

        $result = (new MgPecasAutomotivasCatalogScraper('https://mgpecasautomotivas.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
