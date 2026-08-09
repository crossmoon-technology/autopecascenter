<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\Basso3bCatalogScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Basso3bCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function listingPage(array $items): string
    {
        $rows = collect($items)->map(fn (array $item) => <<<HTML
            <tr><td><a href="/Articulo/Details/{$item['id']}">{$item['codigo']}</a></td></tr>
            HTML)->implode('');

        return "<html><body><table>{$rows}</table></body></html>";
    }

    private function detailPage(string $codigo): string
    {
        return <<<HTML
            <dl class="dl-horizontal">
                <dt>Tipo Prod</dt>
                <dd>V&#225;lvulas</dd>
                <dt>Producto</dt>
                <dd>{$codigo}</dd>
            </dl>
            <div id="aplicacionesModelos"><table><tr><th></th><th>Motor</th><th>Marca</th><th>Modelo</th><th>Anos</th></tr></table></div>
            <div id="fabricantes"><table><tr><th>Fabricante</th><th>Intercambio</th></tr></table></div>
            <div id="Imagen"><img id="zoom_01" src="/content/imagenes/small/{$codigo}.jpg" /></div>
            HTML;
    }

    public function test_scrapes_listing_and_fetches_detail_for_each_product(): void
    {
        Http::fake([
            'basso.com.ar/NroBasso*page=1*' => Http::response($this->listingPage([
                ['id' => 1, 'codigo' => '1001-AC'],
            ]), 200),
            'basso.com.ar/NroBasso*page=2*' => Http::response($this->listingPage([]), 200),
            'basso.com.ar/Articulo/Details/1' => Http::response($this->detailPage('1001-AC'), 200),
        ]);

        $result = (new Basso3bCatalogScraper('https://3bcatalogo.basso.com.ar'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('1001-AC', $result->products[0]['codigo']);
        $this->assertSame('https://3bcatalogo.basso.com.ar/content/imagenes/small/1001-AC.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_the_listing_until_an_empty_page(): void
    {
        Http::fake([
            'basso.com.ar/NroBasso*page=1*' => Http::response($this->listingPage([
                ['id' => 1, 'codigo' => 'A1'],
            ]), 200),
            'basso.com.ar/NroBasso*page=2*' => Http::response($this->listingPage([
                ['id' => 2, 'codigo' => 'A2'],
            ]), 200),
            'basso.com.ar/NroBasso*page=3*' => Http::response($this->listingPage([]), 200),
            'basso.com.ar/Articulo/Details/1' => Http::response($this->detailPage('A1'), 200),
            'basso.com.ar/Articulo/Details/2' => Http::response($this->detailPage('A2'), 200),
        ]);

        $result = (new Basso3bCatalogScraper('https://3bcatalogo.basso.com.ar'))->scrape(null);

        $this->assertSame(['A1', 'A2'], array_column($result->products, 'codigo'));
    }

    public function test_skips_a_listing_when_its_detail_page_cannot_be_parsed(): void
    {
        Http::fake([
            'basso.com.ar/NroBasso*page=1*' => Http::response($this->listingPage([
                ['id' => 1, 'codigo' => 'A1'],
            ]), 200),
            'basso.com.ar/NroBasso*page=2*' => Http::response($this->listingPage([]), 200),
            'basso.com.ar/Articulo/Details/1' => Http::response('<html>sem dados</html>', 200),
        ]);

        $result = (new Basso3bCatalogScraper('https://3bcatalogo.basso.com.ar'))->scrape(null);

        $this->assertCount(0, $result->products);
    }

    public function test_skips_entirely_when_the_listing_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'basso.com.ar/NroBasso*page=1*' => Http::response($this->listingPage([
                ['id' => 1, 'codigo' => 'A1'],
            ]), 200),
            'basso.com.ar/NroBasso*page=2*' => Http::response($this->listingPage([]), 200),
            'basso.com.ar/Articulo/Details/1' => Http::response($this->detailPage('A1'), 200),
        ]);

        $scraper = new Basso3bCatalogScraper('https://3bcatalogo.basso.com.ar');
        $first = $scraper->scrape(null);

        // 2 páginas de listagem (page=1, page=2 vazia) + 1 detalhe — não há
        // como calcular o fingerprint sem antes paginar a listagem inteira,
        // já que este site não tem nenhum marcador barato de total/última
        // atualização (ao contrário da C123).
        Http::assertSentCount(3);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
        // +2 páginas de listagem de novo pra recalcular o fingerprint, mas
        // nenhuma chamada de detalhe — pulou antes de buscar qualquer peça.
        Http::assertSentCount(5);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'basso.com.ar/NroBasso*page=1*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['id' => 1, 'codigo' => 'A1'],
                ]), 200),
            'basso.com.ar/NroBasso*page=2*' => Http::response($this->listingPage([]), 200),
            'basso.com.ar/Articulo/Details/1' => Http::response($this->detailPage('A1'), 200),
        ]);

        $result = (new Basso3bCatalogScraper('https://3bcatalogo.basso.com.ar'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
