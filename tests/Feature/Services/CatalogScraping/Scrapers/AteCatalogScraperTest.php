<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\AteCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AteCatalogScraperTest extends TestCase
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
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/ATE*detalhes.php*' => Http::response($this->detailPage('X1', ['A2C85848800']), 200),
        ]);

        $result = (new AteCatalogScraper('https://catalogoexpresso.com.br/ATE'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('X1', $result->products[0]['codigo']);
        $this->assertSame(['A2C85848800'], $result->products[0]['conversoes']);
        $this->assertSame('GRUPO', $result->products[0]['grupo']);
        $this->assertSame('SUBGRUPO', $result->products[0]['subgrupo']);
        // Sem o segmento /dcp/ do MS Motorservice/Hella — o site do ATE linka
        // direto pra /FotoProdWeb/{arquivo}, confirmado ao vivo (ver docblock).
        $this->assertSame('https://www.c123.com.br/CatalogoExpresso/106/FotoProdWeb/X1.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_requests_the_dot_php_endpoints(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/ATE*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        (new AteCatalogScraper('https://catalogoexpresso.com.br/ATE'))->scrape(null);

        Http::assertSent(fn ($request) => $request->url() === 'https://catalogoexpresso.com.br/ATE/resultado.php?cw_ie_tp=0&cw_pgAtual=1');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/ATE/detalhes.php?')
            && str_contains($request->url(), 'CodigoProduto%3C%212%21%3E1'));
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 2), 200),
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([
                ['CodigoProduto' => 2, 'NumeroProduto' => 'X2'],
            ], total: 2), 200),
            'catalogoexpresso.com.br/ATE*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new AteCatalogScraper('https://catalogoexpresso.com.br/ATE'))->scrape(null);

        $this->assertCount(2, $result->products);
    }

    public function test_throws_before_fetching_any_detail_when_the_listing_falls_far_short_of_the_total(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 100), 200),
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([], total: 100), 200),
        ]);

        try {
            (new AteCatalogScraper('https://catalogoexpresso.com.br/ATE'))->scrape(null);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
            // esperado
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'detalhes'));
    }

    /**
     * Reproduz uma falha real: o site do ATE anunciou 1097 peças, mas a
     * paginação terminou numa página vazia de verdade com só 1093 coletadas —
     * a mesma pequena inconsistência de contador já vista na Rheinmetall
     * (mesma plataforma "cw"), agora confirmada aqui também.
     */
    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 11), 200),
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([], total: 11), 200),
            'catalogoexpresso.com.br/ATE*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new AteCatalogScraper('https://catalogoexpresso.com.br/ATE'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    /**
     * Se a paginação nunca alcançou uma página vazia de verdade (parou por
     * ter batido o MAX_PAGES), a tolerância não se aplica — não há garantia
     * nenhuma de que aquilo foi um fim natural do catálogo.
     */
    public function test_does_not_tolerate_any_shortfall_when_no_empty_page_was_ever_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 2), 200),
        ]);

        $scraper = new class('https://catalogoexpresso.com.br/ATE') extends AteCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/ATE*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $scraper = new AteCatalogScraper('https://catalogoexpresso.com.br/ATE');
        $first = $scraper->scrape(null);

        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_skips_a_product_whose_detail_page_cannot_be_parsed(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/ATE*detalhes.php*' => Http::response('<html>sem dados</html>', 200),
        ]);

        $result = (new AteCatalogScraper('https://catalogoexpresso.com.br/ATE'))->scrape(null);

        $this->assertCount(0, $result->products);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/ATE*resultado.php*cw_pgAtual=1*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
                ], total: 1), 200),
            'catalogoexpresso.com.br/ATE*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new AteCatalogScraper('https://catalogoexpresso.com.br/ATE'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
