<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\RoltensCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RoltensCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function listingPage(array $rows, int $total): string
    {
        return json_encode(['total' => $total, 'rows' => $rows]);
    }

    private function row(string $codigo, ?string $url = null): array
    {
        return [
            'codigo' => $codigo,
            'descricao' => "Peça {$codigo}",
            'grupo' => 'Grupo',
            'foto' => "https://roltens.com.br/fotos/{$codigo}.jpg,https://roltens.com.br/fotos/{$codigo}.jpg",
            'saibamais' => $url !== null ? "Saiba mais,{$url}" : null,
        ];
    }

    private function detailPage(array $conversoes = []): string
    {
        $items = collect($conversoes)->map(fn ($c) => "<li><b>Cód. original:</b> {$c}</li>")->implode('');

        return <<<HTML
            <h5>Referências: </h5>
            <ul class="list-texts text-left">{$items}</ul>
            <h5>Aplicação:</h5>
            <ul class="list-texts text-left"></ul>
            HTML;
    }

    public function test_scrapes_listing_and_detail_pages_end_to_end(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*page=1*' => Http::response($this->listingPage([
                $this->row('RT5205', 'https://roltens.com.br/produto/RT5205'),
            ], total: 1), 200),
            'roltens.com.br/produto/RT5205' => Http::response($this->detailPage(['0515H6']), 200),
        ]);

        $result = (new RoltensCatalogScraper('https://catalogo.roltens.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('RT5205', $result->products[0]['codigo']);
        $this->assertSame(['0515H6'], $result->products[0]['conversoes']);
        $this->assertSame('Grupo', $result->products[0]['grupo']);
        $this->assertSame('https://roltens.com.br/fotos/RT5205.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_requests_the_listagem_endpoint_with_the_page_param(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*' => Http::response($this->listingPage([
                $this->row('X1'),
            ], total: 1), 200),
        ]);

        (new RoltensCatalogScraper('https://catalogo.roltens.com.br'))->scrape(null);

        Http::assertSent(fn ($request) => $request->url() === 'https://catalogo.roltens.com.br/produtos/listagem?page=1');
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*page=1*' => Http::response($this->listingPage([
                $this->row('X1'),
            ], total: 2), 200),
            'catalogo.roltens.com.br/produtos/listagem*page=2*' => Http::response($this->listingPage([
                $this->row('X2'),
            ], total: 2), 200),
        ]);

        $result = (new RoltensCatalogScraper('https://catalogo.roltens.com.br'))->scrape(null);

        $this->assertSame(['X1', 'X2'], array_column($result->products, 'codigo'));
    }

    /**
     * Roltens não retorna página vazia ao passar do fim de verdade (ela
     * simplesmente repete a última página) — então, ao contrário do
     * C123CatalogScraper/AteCatalogScraper, não existe sinal de "página
     * vazia de verdade" pra tolerar uma pequena diferença: qualquer
     * shortfall depois do MAX_PAGES é falha dura.
     */
    public function test_throws_when_the_listing_falls_short_of_the_total_even_by_a_little(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*' => Http::response($this->listingPage([
                $this->row('X1'),
            ], total: 2), 200),
        ]);

        $scraper = new class('https://catalogo.roltens.com.br') extends RoltensCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    public function test_throws_when_the_total_cannot_be_determined(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*' => Http::response('não é json', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new RoltensCatalogScraper('https://catalogo.roltens.com.br'))->scrape(null);
    }

    public function test_skips_a_product_without_a_detail_url(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*' => Http::response($this->listingPage([
                $this->row('X1', null),
            ], total: 1), 200),
        ]);

        $result = (new RoltensCatalogScraper('https://catalogo.roltens.com.br'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
    }

    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*' => Http::response($this->listingPage([
                $this->row('X1', 'https://roltens.com.br/produto/X1'),
            ], total: 1), 200),
            'roltens.com.br/produto/X1' => Http::response($this->detailPage(), 200),
        ]);

        $scraper = new RoltensCatalogScraper('https://catalogo.roltens.com.br');
        $first = $scraper->scrape(null);

        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*' => Http::response($this->listingPage([
                $this->row('X1', 'https://roltens.com.br/produto/X1'),
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'catalogo.roltens.com.br/produtos/listagem*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    $this->row('X1'),
                ], total: 1), 200),
        ]);

        $result = (new RoltensCatalogScraper('https://catalogo.roltens.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
