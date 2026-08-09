<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\BiagioTurbosCatalogScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BiagioTurbosCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function detailJson(string $codigo): string
    {
        return json_encode([
            'turbo' => ['partNumber' => $codigo, 'modelo' => 'BBV145AT', 'arquivosLeves' => [], 'ligacoes' => []],
            'tiposReferencias' => [],
        ]);
    }

    public function test_scrapes_sequential_ids_until_the_consecutive_miss_tolerance_is_reached(): void
    {
        Http::fake([
            'catalogo.biagioturbos.com.br/api/turbos/1' => Http::response($this->detailJson('T1'), 200),
            'catalogo.biagioturbos.com.br/api/turbos/2' => Http::response($this->detailJson('T2'), 200),
            'catalogo.biagioturbos.com.br/api/turbos/*' => Http::response('Not Found', 404),
        ]);

        $scraper = new class('https://catalogo.biagioturbos.com.br') extends BiagioTurbosCatalogScraper
        {
            protected const int MAX_ID = 50;
        };

        $result = $scraper->scrape(null);

        $this->assertSame(['T1', 'T2'], array_column($result->products, 'codigo'));
        $this->assertNotNull($result->source_version);
    }

    /**
     * Reproduz o comportamento real do site: alguns ids no meio da faixa não
     * existem (registros apagados) — um 404 isolado não pode interromper a
     * raspagem antes de alcançar o fim de verdade.
     */
    public function test_tolerates_an_isolated_404_gap_in_the_middle_of_the_range(): void
    {
        Http::fake([
            'catalogo.biagioturbos.com.br/api/turbos/1' => Http::response($this->detailJson('T1'), 200),
            'catalogo.biagioturbos.com.br/api/turbos/2' => Http::response('Not Found', 404),
            'catalogo.biagioturbos.com.br/api/turbos/3' => Http::response($this->detailJson('T3'), 200),
            'catalogo.biagioturbos.com.br/api/turbos/*' => Http::response('Not Found', 404),
        ]);

        $scraper = new class('https://catalogo.biagioturbos.com.br') extends BiagioTurbosCatalogScraper
        {
            protected const int MAX_ID = 50;
        };

        $result = $scraper->scrape(null);

        $this->assertSame(['T1', 'T3'], array_column($result->products, 'codigo'));
    }

    public function test_stops_scanning_once_the_miss_tolerance_is_reached_without_hitting_max_id(): void
    {
        Http::fake([
            'catalogo.biagioturbos.com.br/api/turbos/1' => Http::response($this->detailJson('T1'), 200),
            'catalogo.biagioturbos.com.br/api/turbos/*' => Http::response('Not Found', 404),
        ]);

        $scraper = new class('https://catalogo.biagioturbos.com.br') extends BiagioTurbosCatalogScraper
        {
            protected const int MAX_ID = 1000;
        };

        $result = $scraper->scrape(null);

        $this->assertCount(1, $result->products);
        // 1 acerto + tolerância de 30 falhas consecutivas, não as 1000 ids inteiras.
        $this->assertLessThan(40, Http::recorded()->count());
    }

    public function test_skips_entirely_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'catalogo.biagioturbos.com.br/api/turbos/1' => Http::response($this->detailJson('T1'), 200),
            'catalogo.biagioturbos.com.br/api/turbos/*' => Http::response('Not Found', 404),
        ]);

        $scraper = new class('https://catalogo.biagioturbos.com.br') extends BiagioTurbosCatalogScraper
        {
            protected const int MAX_ID = 40;
        };

        $first = $scraper->scrape(null);
        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'catalogo.biagioturbos.com.br/api/turbos/1' => Http::sequence()
                ->pushStatus(500)
                ->push($this->detailJson('T1'), 200),
            'catalogo.biagioturbos.com.br/api/turbos/*' => Http::response('Not Found', 404),
        ]);

        $scraper = new class('https://catalogo.biagioturbos.com.br') extends BiagioTurbosCatalogScraper
        {
            protected const int MAX_ID = 40;
        };

        $result = $scraper->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
