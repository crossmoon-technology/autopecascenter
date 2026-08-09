<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\FaniaCatalogScraper;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FaniaCatalogScraperTest extends TestCase
{
    /**
     * Ao contrário do Willtec/Bel-Ar, res.asp da Fania não carrega produto
     * nenhum nem mTotPrd — só serve pra estabelecer a sessão (cookie) que o
     * resaj.asp exige. O total real só aparece dentro dos próprios batches
     * do resaj.asp, no final, junto com "mAuxAj='00'".
     */
    private function resajAspBody(array $products, int $total, array $crossReferences = []): string
    {
        $mPrd = collect($products)->map(fn (array $p) => "mPrd[{$p['idx']}]=new fP();with(mPrd[{$p['idx']}]){c={$p['c']};n='{$p['n']}';d='{$p['d']}';i='1';t='{$p['t']}';g='{$p['g']}';s='{$p['s']}';p='0';}")->implode('');

        $mRef = collect($crossReferences)->map(function (array $codes, int $codigoInterno) {
            $entries = collect($codes)->map(fn (string $c, int $i) => "f[{$i}]='0X';n[{$i}]='{$c}';")->implode('');

            return "mRef[{$codigoInterno}]=new fR();with(mRef[{$codigoInterno}]){f=new Array();n=new Array();{$entries}}";
        })->implode('');

        $raw = "{$mPrd}{$mRef}mTotPrd={$total};mAuxAj='00';";

        return '1'.urlencode(mb_convert_encoding($raw, 'ISO-8859-1', 'UTF-8'));
    }

    public function test_scrapes_a_single_batch_including_cross_references(): void
    {
        Http::fake([
            'c123.com.br/fania/res.asp' => Http::response('', 200, ['Set-Cookie' => 'ASPSESSIONID=abc123; path=/']),
            'c123.com.br/fania/resaj.asp*' => Http::response($this->resajAspBody([
                ['idx' => 0, 'c' => 442, 'n' => '30-001', 'd' => 'Cabo', 't' => '30-001.jpg', 'g' => 'Grupo', 's' => 'Sub'],
            ], total: 1, crossReferences: [
                442 => ['7333991', 'GM229'],
            ]), 200),
        ]);

        $result = (new FaniaCatalogScraper('https://c123.com.br/fania'))->scrape(null);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'resaj.asp')
                && $request->header('Cookie') === ['ASPSESSIONID=abc123'];
        });
        $this->assertCount(1, $result->products);
        $this->assertSame('30-001', $result->products[0]['codigo']);
        $this->assertSame(['7333991', 'GM229'], $result->products[0]['conversoes']);
        $this->assertSame('https://c123.com.br/fania/FotoProd/dcp/30-001.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_across_batches_until_the_embedded_total_is_reached(): void
    {
        Http::fake([
            'c123.com.br/fania/res.asp' => Http::response('', 200),
            'c123.com.br/fania/resaj.asp*ic=0*' => Http::response($this->resajAspBody([
                ['idx' => 0, 'c' => 1, 'n' => 'A1', 'd' => 'Peça Um', 't' => 'a1.jpg', 'g' => 'G', 's' => 'S'],
            ], total: 2), 200),
            'c123.com.br/fania/resaj.asp*ic=1*' => Http::response($this->resajAspBody([
                ['idx' => 0, 'c' => 2, 'n' => 'A2', 'd' => 'Peça Dois', 't' => 'a2.jpg', 'g' => 'G', 's' => 'S'],
            ], total: 2), 200),
        ]);

        $result = (new FaniaCatalogScraper('https://c123.com.br/fania'))->scrape(null);

        $this->assertSame(['A1', 'A2'], array_column($result->products, 'codigo'));
    }

    public function test_a_product_without_cross_references_gets_null_conversoes(): void
    {
        Http::fake([
            'c123.com.br/fania/res.asp' => Http::response('', 200),
            'c123.com.br/fania/resaj.asp*' => Http::response($this->resajAspBody([
                ['idx' => 0, 'c' => 1, 'n' => 'A1', 'd' => 'Peça Um', 't' => 'a1.jpg', 'g' => 'G', 's' => 'S'],
            ], total: 1), 200),
        ]);

        $result = (new FaniaCatalogScraper('https://c123.com.br/fania'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
    }

    public function test_skips_entirely_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'c123.com.br/fania/res.asp' => Http::response('', 200),
            'c123.com.br/fania/resaj.asp*' => Http::response($this->resajAspBody([
                ['idx' => 0, 'c' => 1, 'n' => 'A1', 'd' => 'Peça Um', 't' => 'a1.jpg', 'g' => 'G', 's' => 'S'],
            ], total: 1), 200),
        ]);

        $scraper = new FaniaCatalogScraper('https://c123.com.br/fania');
        $first = $scraper->scrape(null);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    /**
     * Mesma proteção do C123CatalogScraper original: parar antes do total
     * anunciado nunca pode ser silenciosamente aceito como "concluído".
     */
    public function test_throws_when_a_page_returns_no_products_before_reaching_the_total(): void
    {
        Http::fake([
            'c123.com.br/fania/res.asp' => Http::response('', 200),
            'c123.com.br/fania/resaj.asp*ic=0*' => Http::response($this->resajAspBody([
                ['idx' => 0, 'c' => 1, 'n' => 'A1', 'd' => 'Peça Um', 't' => 'a1.jpg', 'g' => 'G', 's' => 'S'],
            ], total: 5), 200),
            'c123.com.br/fania/resaj.asp*ic=1*' => Http::response('1', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new FaniaCatalogScraper('https://c123.com.br/fania'))->scrape(null);
    }

    public function test_throws_when_a_non_success_status_digit_leaves_the_result_short_of_the_total(): void
    {
        Http::fake([
            'c123.com.br/fania/res.asp' => Http::response('', 200),
            'c123.com.br/fania/resaj.asp*ic=0*' => Http::response($this->resajAspBody([
                ['idx' => 0, 'c' => 1, 'n' => 'A1', 'd' => 'Peça Um', 't' => 'a1.jpg', 'g' => 'G', 's' => 'S'],
            ], total: 5), 200),
            'c123.com.br/fania/resaj.asp*ic=1*' => Http::response('0Erro ao obter dados.', 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new FaniaCatalogScraper('https://c123.com.br/fania'))->scrape(null);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'c123.com.br/fania/res.asp' => Http::response('', 200),
            'c123.com.br/fania/resaj.asp*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->resajAspBody([
                    ['idx' => 0, 'c' => 1, 'n' => 'A1', 'd' => 'Peça Um', 't' => 'a1.jpg', 'g' => 'G', 's' => 'S'],
                ], total: 1), 200),
        ]);

        $result = (new FaniaCatalogScraper('https://c123.com.br/fania'))->scrape(null);

        $this->assertCount(1, $result->products);
    }
}
