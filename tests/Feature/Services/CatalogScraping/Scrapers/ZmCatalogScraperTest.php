<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\ZmCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ZmCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function tiposProdutoResponse(array $tipos): array
    {
        return ['message' => $tipos];
    }

    private function buscaProdutosResponse(array $tipoResponse): array
    {
        return ['message' => [$tipoResponse]];
    }

    public function test_scrapes_every_product_type_end_to_end(): void
    {
        Http::fake([
            'extranet.zm.com.br/api/catalogo-tipos-produto/*' => Http::response($this->tiposProdutoResponse([
                ['cod_tipo_produto' => '1', 'des_tipo_produto' => 'Relés de Partida'],
            ]), 200),
            'extranet.zm.com.br/api/catalogo-busca-produtos' => Http::response($this->buscaProdutosResponse([
                'des_tipo' => 'Relés de Partida',
                'des_campo1' => 'Aplicação',
                'des_campo2' => 'Número Original',
                'produtos' => [
                    [
                        'des_produto_lista_galeria' => 'ZM 1-291',
                        'des_produto_lista' => 'Relé auxiliar de partida ZM 1-291 24v',
                        'val_campo1' => 'DAF',
                        'val_campo2' => '0.333.006.026',
                        'des_caminho_imagem' => 'https://files.zm.com.br/1291.jpg',
                    ],
                ],
            ]), 200),
        ]);

        $result = (new ZmCatalogScraper('https://extranet.zm.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('ZM 1-291', $result->products[0]['codigo']);
        $this->assertSame(['0.333.006.026'], $result->products[0]['conversoes']);
        $this->assertSame('DAF', $result->products[0]['fabricante']);
        $this->assertSame('Relés de Partida', $result->products[0]['grupo']);
        $this->assertNotNull($result->source_version);
    }

    public function test_sends_the_token_server_field_on_every_request(): void
    {
        Http::fake([
            'extranet.zm.com.br/api/catalogo-tipos-produto/*' => Http::response($this->tiposProdutoResponse([
                ['cod_tipo_produto' => '1', 'des_tipo_produto' => 'X'],
            ]), 200),
            'extranet.zm.com.br/api/catalogo-busca-produtos' => Http::response($this->buscaProdutosResponse([
                'des_tipo' => 'X',
                'produtos' => [],
            ]), 200),
        ]);

        (new ZmCatalogScraper('https://extranet.zm.com.br'))->scrape(null);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'catalogo-tipos-produto')
            && str_contains($request->url(), 'token-server/x4654D55s4sazx4584sjiw84Usi'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'catalogo-busca-produtos')
            && $request['token-server'] === 'x4654D55s4sazx4584sjiw84Usi');
    }

    public function test_aggregates_products_across_multiple_types(): void
    {
        Http::fake([
            'extranet.zm.com.br/api/catalogo-tipos-produto/*' => Http::response($this->tiposProdutoResponse([
                ['cod_tipo_produto' => '1', 'des_tipo_produto' => 'Relés de Partida'],
                ['cod_tipo_produto' => '2', 'des_tipo_produto' => 'Parafusos de Roda'],
            ]), 200),
            'extranet.zm.com.br/api/catalogo-busca-produtos' => Http::sequence()
                ->push($this->buscaProdutosResponse([
                    'des_tipo' => 'Relés de Partida',
                    'produtos' => [['des_produto_lista_galeria' => 'ZM 1-1', 'des_produto_lista' => 'Um']],
                ]), 200)
                ->push($this->buscaProdutosResponse([
                    'des_tipo' => 'Parafusos de Roda',
                    'produtos' => [['des_produto_lista_galeria' => 'ZM 2-1', 'des_produto_lista' => 'Dois']],
                ]), 200),
        ]);

        $result = (new ZmCatalogScraper('https://extranet.zm.com.br'))->scrape(null);

        $this->assertSame(['ZM 1-1', 'ZM 2-1'], array_column($result->products, 'codigo'));
    }

    public function test_throws_when_the_product_type_list_cannot_be_fetched(): void
    {
        Http::fake([
            'extranet.zm.com.br/api/catalogo-tipos-produto/*' => Http::response($this->tiposProdutoResponse([]), 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new ZmCatalogScraper('https://extranet.zm.com.br'))->scrape(null);
    }

    public function test_skips_entirely_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'extranet.zm.com.br/api/catalogo-tipos-produto/*' => Http::response($this->tiposProdutoResponse([
                ['cod_tipo_produto' => '1', 'des_tipo_produto' => 'X'],
            ]), 200),
            'extranet.zm.com.br/api/catalogo-busca-produtos' => Http::response($this->buscaProdutosResponse([
                'des_tipo' => 'X',
                'produtos' => [['des_produto_lista_galeria' => 'ZM 1-1', 'des_produto_lista' => 'Um']],
            ]), 200),
        ]);

        $scraper = new ZmCatalogScraper('https://extranet.zm.com.br');
        $first = $scraper->scrape(null);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'extranet.zm.com.br/api/catalogo-tipos-produto/*' => Http::response($this->tiposProdutoResponse([
                ['cod_tipo_produto' => '1', 'des_tipo_produto' => 'X'],
            ]), 200),
            'extranet.zm.com.br/api/catalogo-busca-produtos' => Http::sequence()
                ->pushStatus(500)
                ->push($this->buscaProdutosResponse([
                    'des_tipo' => 'X',
                    'produtos' => [['des_produto_lista_galeria' => 'ZM 1-1', 'des_produto_lista' => 'Um']],
                ]), 200),
        ]);

        $result = (new ZmCatalogScraper('https://extranet.zm.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
