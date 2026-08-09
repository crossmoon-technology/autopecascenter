<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\IksCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class IksCatalogScraperTest extends TestCase
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
            'iks.com.br/busca*cw_pgAtual=1*' => Http::response($this->listingPage([
                [
                    'CodigoProduto' => 1228,
                    'NumeroProduto' => '10101',
                    'DescricaoProduto' => 'CABO DE ACELERADOR',
                    'ArquivoFotoProduto' => '10101.jpg',
                    'FabricantesAplicacao' => [
                        ['DescricaoFabricante' => 'MERCEDES-BENZ', 'Aplicacoes' => [['DescricaoAplicacao' => 'MB 180']]],
                    ],
                    'ReferenciasCruzada' => [
                        ['DescricaoFabricante' => 'ORIGINAL', 'NumerosProduto' => [['NumeroProduto' => '63.13.000.530']]],
                    ],
                ],
            ], total: 1), 200),
        ]);

        $result = (new IksCatalogScraper('https://iks.com.br'))->scrape(null);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'detalhes'));
        $this->assertCount(1, $result->products);
        $this->assertSame('10101', $result->products[0]['codigo']);
        $this->assertSame('CABO DE ACELERADOR', $result->products[0]['descricao']);
        $this->assertSame(['63.13.000.530'], $result->products[0]['conversoes']);
        $this->assertSame('MERCEDES-BENZ MB 180', $result->products[0]['aplicacao']);
        $this->assertSame('https://www.ideia2001.com.br/CatalogoExpresso/417/FotoProdWeb/dcp/10101.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'iks.com.br/busca*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 2), 200),
            'iks.com.br/busca*cw_pgAtual=2*' => Http::response($this->listingPage([
                ['CodigoProduto' => 2, 'NumeroProduto' => 'X2', 'DescricaoProduto' => 'Dois'],
            ], total: 2), 200),
        ]);

        $result = (new IksCatalogScraper('https://iks.com.br'))->scrape(null);

        $this->assertCount(2, $result->products);
        $this->assertSame(['X1', 'X2'], array_column($result->products, 'codigo'));
    }

    public function test_throws_before_the_shortfall_tolerance_when_no_empty_page_was_reached(): void
    {
        Http::fake([
            'iks.com.br/busca*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 100), 200),
        ]);

        $scraper = new class('https://iks.com.br') extends IksCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'iks.com.br/busca*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 11), 200),
            'iks.com.br/busca*cw_pgAtual=2*' => Http::response($this->listingPage([], total: 11), 200),
        ]);

        $result = (new IksCatalogScraper('https://iks.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_skips_entirely_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'iks.com.br/busca*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
            ], total: 1), 200),
        ]);

        $scraper = new IksCatalogScraper('https://iks.com.br');
        $first = $scraper->scrape(null);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'iks.com.br/busca*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['CodigoProduto' => 1, 'NumeroProduto' => 'X1', 'DescricaoProduto' => 'Um'],
                ], total: 1), 200),
        ]);

        $result = (new IksCatalogScraper('https://iks.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
