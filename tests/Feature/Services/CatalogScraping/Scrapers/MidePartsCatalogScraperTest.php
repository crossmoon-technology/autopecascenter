<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\MidePartsCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MidePartsCatalogScraperTest extends TestCase
{
    /**
     * Um padrão de Http::fake() que não bate com a URL real cai pra rede de
     * verdade por padrão — preventStrayRequests() faz qualquer chamada não
     * coberta pelos fakes estourar aqui em vez de vazar pro site real.
     */
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

    private function detailPage(string $codigo, ?array $conversoes = null): string
    {
        $referenciasCruzada = $conversoes !== null
            ? [['DescricaoFabricante' => 'X', 'NumerosProduto' => collect($conversoes)->map(fn ($c) => ['NumeroProduto' => $c])->all()]]
            : [];

        $json = json_encode(['data' => [[
            'NumeroProduto' => $codigo,
            'DescricaoProduto' => "PEÇA {$codigo}",
            'DescricaoFabricante' => 'X',
            'DescricaoGrupoProduto' => 'GRUPO',
            'DescricaoSubGrupoProduto' => 'SUBGRUPO',
            'ArquivoFotoProduto' => "{$codigo}.jpg",
            'FabricantesAplicacao' => [],
            'ReferenciasCruzada' => $referenciasCruzada,
        ]]]);

        return <<<HTML
            <html><body>
            <script id="__CW_DATA_DETALHES_PRODUTO__" type="application/json">{$json}</script>
            </body></html>
            HTML;
    }

    public function test_scrapes_listing_and_detail_pages_end_to_end(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/mideparts*detalhes*' => Http::response($this->detailPage('X1', ['906 030 03 20']), 200),
        ]);

        $result = (new MidePartsCatalogScraper('https://catalogoexpresso.com.br/mideparts'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('X1', $result->products[0]['codigo']);
        $this->assertSame(['9060300320'], $result->products[0]['conversoes']);
        $this->assertSame('GRUPO', $result->products[0]['grupo']);
        $this->assertSame('SUBGRUPO', $result->products[0]['subgrupo']);
        // Sem o segmento /dcp/ dos outros fabricantes desse mesmo backend — o
        // site do Mide Parts linka direto pra /FotoProdWeb/{arquivo}, confirmado
        // ao vivo (ver docblock do scraper).
        $this->assertSame('https://www.c123.com.br/CatalogoExpresso/590/FotoProdWeb/X1.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_requests_the_php_less_endpoints(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/mideparts*detalhes*' => Http::response($this->detailPage('X1'), 200),
        ]);

        (new MidePartsCatalogScraper('https://catalogoexpresso.com.br/mideparts'))->scrape(null);

        Http::assertSent(fn ($request) => $request->url() === 'https://catalogoexpresso.com.br/mideparts/resultado?cw_ie_tp=0&cw_pgAtual=1');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/mideparts/detalhes?')
            && str_contains($request->url(), 'retornaJSON=true')
            && str_contains($request->url(), 'CodigoProduto%3C%212%21%3E1'));
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 2), 200),
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=2*' => Http::response($this->listingPage([
                ['CodigoProduto' => 2, 'NumeroProduto' => 'X2'],
            ], total: 2), 200),
            'catalogoexpresso.com.br/mideparts*detalhes*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new MidePartsCatalogScraper('https://catalogoexpresso.com.br/mideparts'))->scrape(null);

        $this->assertCount(2, $result->products);
    }

    public function test_throws_before_fetching_any_detail_when_the_listing_falls_short_of_the_total(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 5), 200),
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=2*' => Http::response($this->listingPage([], total: 5), 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new MidePartsCatalogScraper('https://catalogoexpresso.com.br/mideparts'))->scrape(null);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'detalhes'));
    }

    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/mideparts*detalhes*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $scraper = new MidePartsCatalogScraper('https://catalogoexpresso.com.br/mideparts');
        $first = $scraper->scrape(null);

        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_skips_a_product_whose_detail_page_cannot_be_parsed(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/mideparts*detalhes*' => Http::response('<html>sem dados</html>', 200),
        ]);

        $result = (new MidePartsCatalogScraper('https://catalogoexpresso.com.br/mideparts'))->scrape(null);

        $this->assertCount(0, $result->products);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/mideparts*resultado*cw_pgAtual=1*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
                ], total: 1), 200),
            'catalogoexpresso.com.br/mideparts*detalhes*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new MidePartsCatalogScraper('https://catalogoexpresso.com.br/mideparts'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
