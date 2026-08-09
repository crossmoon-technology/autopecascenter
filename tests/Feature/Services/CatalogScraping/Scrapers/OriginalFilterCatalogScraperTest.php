<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\OriginalFilterCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OriginalFilterCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function listingPage(array $items, int $total): string
    {
        $json = json_encode(['data' => $items]);

        return <<<HTML
            <html><body>
            <strong id="cw-total-resultado-plural">{$total}</strong>
            <script id="__CW_DATA_LISTA_RESULTADO__" type="application/json">{$json}</script>
            </body></html>
            HTML;
    }

    public function test_scrapes_listing_including_cross_reference_with_no_detail_fetch(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/original-filter/resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                [
                    'CodigoProduto' => 7425,
                    'NumeroProduto' => 'OFA2000C',
                    'DescricaoProduto' => 'Elemento Filtrante do Ar - Primário',
                    'DescricaoGrupoProduto' => 'FILTRO DE AR',
                    'ArquivoFotoProduto' => 'OFA2000C.jpg',
                    'FabricantesAplicacao' => [
                        ['DescricaoFabricante' => 'AGRALE', 'Aplicacoes' => [['DescricaoAplicacao' => 'MA 7.0']]],
                    ],
                    'ReferenciasCruzada' => [
                        ['DescricaoFabricante' => 'BOSCH', 'NumerosProduto' => [['NumeroProduto' => 'AB3535']]],
                    ],
                ],
            ], total: 1), 200),
        ]);

        $result = (new OriginalFilterCatalogScraper('https://catalogoexpresso.com.br/original-filter'))->scrape(null);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'detalhes'));
        $this->assertCount(1, $result->products);
        $this->assertSame('OFA2000C', $result->products[0]['codigo']);
        $this->assertSame('Elemento Filtrante do Ar - Primário', $result->products[0]['descricao']);
        $this->assertSame('FILTRO DE AR', $result->products[0]['grupo']);
        $this->assertSame(['AB3535'], $result->products[0]['conversoes']);
        $this->assertSame('AGRALE MA 7.0', $result->products[0]['aplicacao']);
        $this->assertSame('https://www.c123.com.br/CatalogoExpresso/149/FotoProdWeb/dcp/OFA2000C.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/original-filter/resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 2), 200),
            'catalogoexpresso.com.br/original-filter/resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([
                ['NumeroProduto' => 'X2', 'DescricaoProduto' => 'Dois'],
            ], total: 2), 200),
        ]);

        $result = (new OriginalFilterCatalogScraper('https://catalogoexpresso.com.br/original-filter'))->scrape(null);

        $this->assertCount(2, $result->products);
        $this->assertSame(['X1', 'X2'], array_column($result->products, 'codigo'));
    }

    public function test_throws_before_the_shortfall_tolerance_when_no_empty_page_was_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/original-filter/resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 100), 200),
        ]);

        $scraper = new class('https://catalogoexpresso.com.br/original-filter') extends OriginalFilterCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/original-filter/resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 11), 200),
            'catalogoexpresso.com.br/original-filter/resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([], total: 11), 200),
        ]);

        $result = (new OriginalFilterCatalogScraper('https://catalogoexpresso.com.br/original-filter'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_skips_entirely_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/original-filter/resultado.php*' => Http::response($this->listingPage([
                ['NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 1), 200),
        ]);

        $scraper = new OriginalFilterCatalogScraper('https://catalogoexpresso.com.br/original-filter');
        $first = $scraper->scrape(null);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/original-filter/resultado.php*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
                ], total: 1), 200),
        ]);

        $result = (new OriginalFilterCatalogScraper('https://catalogoexpresso.com.br/original-filter'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
