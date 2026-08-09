<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\NatIndustriaCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class NatIndustriaCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function sitemap(array $entries): string
    {
        $urls = collect($entries)->map(fn (array $e) => "<url><loc>{$e['url']}</loc><lastmod>{$e['lastmod']}</lastmod></url>")->implode('');

        return "<urlset>{$urls}</urlset>";
    }

    private function detailPage(string $title, string $swatchCaption, string $aplicacao = 'Golf 99/13'): string
    {
        return <<<HTML
            <img id="largeImage" class="product-image" src="https://cdn/x.jpg">
            <h1 class="single-product_title">{$title}</h1>
            <div class="single-product_content">{$aplicacao}<div style=""><p style="">{$swatchCaption}</p></div></div>
            <div class="widget-shared"></div>
            HTML;
    }

    public function test_scrapes_the_sitemap_and_every_detail_page(): void
    {
        Http::fake([
            'natindustria.com.br/wp-sitemap-posts-produto-1.xml' => Http::response($this->sitemap([
                ['url' => 'https://natindustria.com.br/produto/x/', 'lastmod' => '2021-01-19T10:46:13+00:00'],
            ]), 200),
            'natindustria.com.br/produto/x/' => Http::response($this->detailPage('difusor de ar - 200605', '200605 - Preto'), 200),
        ]);

        $result = (new NatIndustriaCatalogScraper('https://natindustria.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('200605', $result->products[0]['codigo']);
        $this->assertNotNull($result->source_version);
    }

    public function test_flattens_multiple_variants_from_a_single_detail_page(): void
    {
        Http::fake([
            'natindustria.com.br/wp-sitemap-posts-produto-1.xml' => Http::response($this->sitemap([
                ['url' => 'https://natindustria.com.br/produto/apoio/', 'lastmod' => '2021-01-19T10:46:13+00:00'],
            ]), 200),
            'natindustria.com.br/produto/apoio/' => Http::response(
                '<img class="product-image" src="https://cdn/x.jpg">'
                .'<h1 class="single-product_title">Apoio de Braço Golf</h1>'
                .'<div class="single-product_content">Golf 99/13'
                .'<div style=""><p style="">Preto c/Linha Preta - 100700</p></div>'
                .'<div style=""><p style="">Cinza c/Linha Cinza - 100701</p></div>'
                .'</div><div class="widget-shared"></div>',
                200
            ),
        ]);

        $result = (new NatIndustriaCatalogScraper('https://natindustria.com.br'))->scrape(null);

        $this->assertSame(['100700', '100701'], array_column($result->products, 'codigo'));
    }

    public function test_throws_when_the_sitemap_has_no_urls(): void
    {
        Http::fake([
            'natindustria.com.br/wp-sitemap-posts-produto-1.xml' => Http::response('<urlset></urlset>', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new NatIndustriaCatalogScraper('https://natindustria.com.br'))->scrape(null);
    }

    /**
     * O fingerprint vem só do sitemap (url+lastmod) — nem uma página de
     * detalhe deveria ser buscada quando nada mudou.
     */
    public function test_skips_every_detail_fetch_when_the_sitemap_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'natindustria.com.br/wp-sitemap-posts-produto-1.xml' => Http::response($this->sitemap([
                ['url' => 'https://natindustria.com.br/produto/x/', 'lastmod' => '2021-01-19T10:46:13+00:00'],
            ]), 200),
            'natindustria.com.br/produto/x/' => Http::response($this->detailPage('difusor de ar - 200605', '200605 - Preto'), 200),
        ]);

        $scraper = new NatIndustriaCatalogScraper('https://natindustria.com.br');
        $first = $scraper->scrape(null);

        Http::fake([
            'natindustria.com.br/wp-sitemap-posts-produto-1.xml' => Http::response($this->sitemap([
                ['url' => 'https://natindustria.com.br/produto/x/', 'lastmod' => '2021-01-19T10:46:13+00:00'],
            ]), 200),
        ]);

        // O segundo Http::fake() acima NÃO registra a página de detalhe —
        // com preventStrayRequests() ativo, se o scraper tentasse buscá-la
        // mesmo assim, isso já teria lançado uma exceção aqui.
        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_re_fetches_when_a_lastmod_changes(): void
    {
        // Http::fake() registrado duas vezes pra MESMA URL não troca a
        // resposta (o primeiro stub continua vencendo) — por isso usa
        // Http::sequence() aqui pra dar duas respostas diferentes em
        // chamadas sucessivas à mesma URL do sitemap.
        Http::fake([
            'natindustria.com.br/wp-sitemap-posts-produto-1.xml' => Http::sequence()
                ->push($this->sitemap([
                    ['url' => 'https://natindustria.com.br/produto/x/', 'lastmod' => '2021-01-19T10:46:13+00:00'],
                ]), 200)
                ->push($this->sitemap([
                    ['url' => 'https://natindustria.com.br/produto/x/', 'lastmod' => '2023-01-01T00:00:00+00:00'],
                ]), 200),
            'natindustria.com.br/produto/x/' => Http::response($this->detailPage('difusor de ar - 200605', '200605 - Preto'), 200),
        ]);

        $scraper = new NatIndustriaCatalogScraper('https://natindustria.com.br');
        $first = $scraper->scrape(null);
        $second = $scraper->scrape($first->source_version);

        $this->assertNotNull($second);
        $this->assertNotSame($first->source_version, $second->source_version);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'natindustria.com.br/wp-sitemap-posts-produto-1.xml' => Http::sequence()
                ->pushStatus(500)
                ->push($this->sitemap([
                    ['url' => 'https://natindustria.com.br/produto/x/', 'lastmod' => '2021-01-19T10:46:13+00:00'],
                ]), 200),
            'natindustria.com.br/produto/x/' => Http::response($this->detailPage('difusor de ar - 200605', '200605 - Preto'), 200),
        ]);

        $result = (new NatIndustriaCatalogScraper('https://natindustria.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
