<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\RpdCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RpdCatalogScraperTest extends TestCase
{
    private function page(array $rows, int $total): string
    {
        $tables = collect($rows)->map(function (array $row) {
            $span = $row['conversoes'] ?? null;
            $spanHtml = $span !== null ? '<br /><span>'.implode('<br />', $span).'</span>' : '';

            return <<<HTML
                <div class="cabeitem"><div><p>{$row['montadora']} - {$row['grupo']}</p></div></div>
                <table class="listapecas"><tr>
                    <td class="lpecaimg"><img alt="{$row['codigo']}" src="catalogo/{$row['codigo']}.jpg" /></td>
                    <td class="lpecanum"><p class="listapecanum">{$row['codigo']}{$spanHtml}</p></td>
                    <td class="lpecadescricao">{$row['descricao']}</td>
                    <td class="lpecaaplicaco">{$row['aplicacao']}</td>
                    <td class="lpecaano">todos</td>
                </tr></table>
                HTML;
        })->implode('');

        return <<<HTML
            <html><body>
            <table><tr><td class="tdinfolista">Total listado {$total}</td></tr></table>
            {$tables}
            </body></html>
            HTML;
    }

    private function row(string $codigo, ?array $conversoes = null): array
    {
        return [
            'codigo' => $codigo,
            'descricao' => "PEÇA {$codigo}",
            'montadora' => 'CHEVROLET',
            'grupo' => 'BUCHAS',
            'aplicacao' => 'ONIX',
            'conversoes' => $conversoes,
        ];
    }

    public function test_scrapes_a_single_page_when_it_covers_the_whole_total(): void
    {
        Http::fake([
            'rpdborrachas.com.br*p=1*' => Http::response($this->page([
                $this->row('10106', ['95.463.563']),
            ], total: 1), 200),
        ]);

        $result = (new RpdCatalogScraper('https://www.rpdborrachas.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('10106', $result->products[0]['codigo']);
        $this->assertSame(['95463563'], $result->products[0]['conversoes']);
        $this->assertSame('CHEVROLET', $result->products[0]['montadora']);
        $this->assertSame('BUCHAS', $result->products[0]['grupo']);
        $this->assertSame('https://www.rpdborrachas.com.br/catalogo/10106.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
        Http::assertSentCount(1);
    }

    public function test_paginates_until_the_total_is_reached(): void
    {
        Http::fake([
            'rpdborrachas.com.br*p=1*' => Http::response($this->page([
                $this->row('A1'),
            ], total: 2), 200),
            'rpdborrachas.com.br*p=2*' => Http::response($this->page([
                $this->row('A2'),
            ], total: 2), 200),
        ]);

        $result = (new RpdCatalogScraper('https://www.rpdborrachas.com.br'))->scrape(null);

        $this->assertSame(['A1', 'A2'], array_column($result->products, 'codigo'));
        Http::assertSentCount(2);
    }

    /**
     * Reproduz a proteção equivalente ao MAX_PAGES/mTotPrd da C123: se a
     * paginação terminar (página vazia) antes de bater o total anunciado, é
     * falha dura — nunca sobrescreve o catálogo com dado parcial.
     */
    public function test_throws_when_an_empty_page_leaves_the_result_short_of_the_total(): void
    {
        Http::fake([
            'rpdborrachas.com.br*p=1*' => Http::response($this->page([
                $this->row('A1'),
            ], total: 5), 200),
            'rpdborrachas.com.br*p=2*' => Http::response($this->page([], total: 5), 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new RpdCatalogScraper('https://www.rpdborrachas.com.br'))->scrape(null);
    }

    public function test_returns_null_when_the_fingerprint_matches_the_known_version(): void
    {
        Http::fake([
            'rpdborrachas.com.br*p=1*' => Http::response($this->page([
                $this->row('A1'),
            ], total: 1), 200),
        ]);

        $scraper = new RpdCatalogScraper('https://www.rpdborrachas.com.br');
        $first = $scraper->scrape(null);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'rpdborrachas.com.br*p=1*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->page([
                    $this->row('A1'),
                ], total: 1), 200),
        ]);

        $result = (new RpdCatalogScraper('https://www.rpdborrachas.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
