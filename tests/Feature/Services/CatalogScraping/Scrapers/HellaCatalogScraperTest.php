<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\HellaCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HellaCatalogScraperTest extends TestCase
{
    /**
     * Um padrão de Http::fake() que não bate com a URL real cai pra rede de
     * verdade por padrão (já aconteceu com a RPD nesta mesma sessão) —
     * preventStrayRequests() faz qualquer chamada não coberta pelos fakes
     * estourar aqui em vez de vazar pro site real.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function listingChunk(array $produtos, int $total): string
    {
        return '8:{"state":{"data":{"tipo":"PRODUTO","produtos":'.json_encode($produtos).',"totalResultado":'.$total.'}}}';
    }

    private function detailChunk(string $codigo, ?array $conversoes = null): string
    {
        $referenciasCruzada = $conversoes !== null
            ? [['DescricaoFabricante' => 'X', 'NumerosProduto' => collect($conversoes)->map(fn ($c) => ['NumeroProduto' => $c])->all()]]
            : [];

        $produto = json_encode([
            'CodigoProduto' => 1,
            'NumeroProduto' => $codigo,
            'DescricaoProduto' => "PEÇA {$codigo}",
            'ArquivoFotoProduto' => "{$codigo}.jpg",
            'FabricantesAplicacao' => [],
            'ReferenciasCruzada' => $referenciasCruzada,
        ]);

        return '8:{"state":{"data":'.$produto.'}}';
    }

    public function test_scrapes_listing_and_detail_pages_end_to_end(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/hella*pagina=1*' => Http::response($this->listingChunk([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/hella/produto/1*' => Http::response($this->detailChunk('X1', ['906 030 03 20']), 200),
        ]);

        $result = (new HellaCatalogScraper('https://catalogoexpresso.com.br/hella'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('X1', $result->products[0]['codigo']);
        $this->assertSame(['9060300320'], $result->products[0]['conversoes']);
        $this->assertSame('https://www.c123.com.br/CatalogoExpresso/462/FotoProdWeb/dcp/X1.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/hella*pagina=1*' => Http::response($this->listingChunk([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 2), 200),
            'catalogoexpresso.com.br/hella*pagina=2*' => Http::response($this->listingChunk([
                ['CodigoProduto' => 2, 'NumeroProduto' => 'X2'],
            ], total: 2), 200),
            'catalogoexpresso.com.br/hella/produto/*' => Http::response($this->detailChunk('X1'), 200),
        ]);

        $result = (new HellaCatalogScraper('https://catalogoexpresso.com.br/hella'))->scrape(null);

        $this->assertCount(2, $result->products);
    }

    public function test_throws_before_fetching_any_detail_when_the_listing_falls_short_of_the_total(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/hella*pagina=1*' => Http::response($this->listingChunk([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 5), 200),
            'catalogoexpresso.com.br/hella*pagina=2*' => Http::response($this->listingChunk([], total: 5), 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new HellaCatalogScraper('https://catalogoexpresso.com.br/hella'))->scrape(null);
    }

    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/hella*pagina=1*' => Http::response($this->listingChunk([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/hella/produto/*' => Http::response($this->detailChunk('X1'), 200),
        ]);

        $scraper = new HellaCatalogScraper('https://catalogoexpresso.com.br/hella');
        $first = $scraper->scrape(null);

        Http::fake([
            'catalogoexpresso.com.br/hella*pagina=1*' => Http::response($this->listingChunk([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_skips_a_product_whose_detail_page_cannot_be_parsed(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/hella*pagina=1*' => Http::response($this->listingChunk([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catalogoexpresso.com.br/hella/produto/*' => Http::response('5:["$","div",null,{}]', 200),
        ]);

        $result = (new HellaCatalogScraper('https://catalogoexpresso.com.br/hella'))->scrape(null);

        $this->assertCount(0, $result->products);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'catalogoexpresso.com.br/hella*pagina=1*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingChunk([
                    ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
                ], total: 1), 200),
            'catalogoexpresso.com.br/hella/produto/*' => Http::response($this->detailChunk('X1'), 200),
        ]);

        $result = (new HellaCatalogScraper('https://catalogoexpresso.com.br/hella'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
