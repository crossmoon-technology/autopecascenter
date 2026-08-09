<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\C123CatalogScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class C123CatalogScraperTest extends TestCase
{
    private function mRefLiterals(array $crossReferences): string
    {
        return collect($crossReferences)->map(function (array $codes, int $codigoInterno) {
            $entries = collect($codes)->map(fn (string $c, int $i) => "f[{$i}]='0X';n[{$i}]='{$c}';")->implode('');

            return "mRef[{$codigoInterno}]=new fR();with(mRef[{$codigoInterno}]){f=new Array();n=new Array();{$entries}}";
        })->implode('');
    }

    private function resAspBody(int $total, string $sourceVersion, array $products, array $crossReferences = []): string
    {
        $literals = collect($products)->map(fn (array $p, int $i) => "mPrd[{$i}]=new fP();with(mPrd[{$i}]){c={$p['c']};n='{$p['n']}';d='{$p['d']}';i='1';t='{$p['t']}';g='{$p['g']}';s='{$p['s']}';p='0';}")->implode('');
        $mRef = $this->mRefLiterals($crossReferences);

        $html = <<<HTML
            <html><body>
            function fP(){this.c=0;this.n='';this.d='';this.i='';this.t='';this.qd='';this.pd='';this.ld='';this.g='';this.s='';this.p=0;}var mPrd=new Array();{$literals}{$mRef}
            var mOB='1',mOBa=1,mOBf='',mTotPrd={$total};
            <DIV id=divUltimaAtualizacao style="POSITION:absolute;">{$sourceVersion}</DIV>
            </body></html>
            HTML;

        return mb_convert_encoding($html, 'ISO-8859-1', 'UTF-8');
    }

    private function resajAspBody(array $products, array $crossReferences = []): string
    {
        $literals = collect($products)->map(fn (array $p) => "mPrd[{$p['idx']}]=new fP();with(mPrd[{$p['idx']}]){c={$p['c']};n='{$p['n']}';d='{$p['d']}';i='1';t='{$p['t']}';g='{$p['g']}';s='{$p['s']}';p='0';}")->implode('');
        $mRef = $this->mRefLiterals($crossReferences);

        $latin1 = mb_convert_encoding("{$literals}{$mRef}", 'ISO-8859-1', 'UTF-8');

        return '1'.urlencode($latin1);
    }

    public function test_single_page_scrape_makes_only_one_request(): void
    {
        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response($this->resAspBody(2, '24-JUL-2026b', [
                ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
                ['c' => 2, 'n' => 'W02', 'd' => 'Peça Dois', 't' => 'w02.jpg', 'g' => 'Grupo A', 's' => 'Sub B'],
            ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/willtec'))->scrape(null);

        Http::assertSentCount(1);
        $this->assertSame('24-JUL-2026b', $result->source_version);
        $this->assertCount(2, $result->products);
        $this->assertSame('W01', $result->products[0]['codigo']);
        $this->assertSame('Peça Um', $result->products[0]['descricao']);
        $this->assertSame('https://c123.com.br/willtec/FotoProd/dcp/w01.jpg', $result->products[0]['imagem_url']);
    }

    /**
     * Uma sequência de centenas de requisições sequenciais eventualmente esbarra
     * em um erro transitório de rede — ->retry() em cada chamada evita que isso
     * derrube a raspagem inteira (que, pro Willtec real, pode levar minutos).
     */
    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::sequence()
                ->pushStatus(500)
                ->push($this->resAspBody(1, '24-JUL-2026b', [
                    ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
                ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/willtec'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_paginates_through_resaj_until_total_is_reached(): void
    {
        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response($this->resAspBody(3, '05-AGO-2026a', [
                ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
            ]), 200, ['Set-Cookie' => 'ASPSESSIONID=abc123; path=/']),
            'c123.com.br/willtec/resaj.asp*' => Http::response($this->resajAspBody([
                ['idx' => 1, 'c' => 2, 'n' => 'W02', 'd' => 'Peça Dois', 't' => 'w02.jpg', 'g' => 'Grupo A', 's' => 'Sub B'],
                ['idx' => 2, 'c' => 3, 'n' => 'W03', 'd' => 'Peça Três', 't' => 'w03.jpg', 'g' => 'Grupo A', 's' => 'Sub C'],
            ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/willtec'))->scrape(null);

        Http::assertSentCount(2);
        $this->assertCount(3, $result->products);
        $this->assertSame(['W01', 'W02', 'W03'], array_column($result->products, 'codigo'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'resaj.asp')
                && $request->header('Cookie') === ['ASPSESSIONID=abc123'];
        });
    }

    public function test_skips_entirely_when_source_version_is_unchanged(): void
    {
        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response($this->resAspBody(1, '24-JUL-2026b', [
                ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
            ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/willtec'))->scrape('24-JUL-2026b');

        $this->assertNull($result);
        Http::assertSentCount(1);
    }

    /**
     * Stopping short of mTotPrd must never be silently accepted as "done" — a
     * real Willtec scrape once landed at 581 of 13385 parts this way (likely
     * rate-limiting from hammering resaj.asp unthrottled) and, worse, would
     * have advanced source_version, making the next scheduled run think
     * nothing changed and skip re-scraping — permanently freezing the
     * catalog at that partial state.
     */
    public function test_throws_when_a_non_success_status_digit_leaves_the_result_short_of_the_total(): void
    {
        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response($this->resAspBody(5, '24-JUL-2026b', [
                ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
            ]), 200),
            'c123.com.br/willtec/resaj.asp*' => Http::response('0Erro ao obter dados.', 200),
        ]);

        $this->expectException(\RuntimeException::class);

        (new C123CatalogScraper('https://c123.com.br/willtec'))->scrape(null);
    }

    public function test_throws_when_a_page_returns_no_products_before_reaching_the_total(): void
    {
        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response($this->resAspBody(5, '24-JUL-2026b', [
                ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
            ]), 200),
            'c123.com.br/willtec/resaj.asp*' => Http::response('1', 200),
        ]);

        $this->expectException(\RuntimeException::class);

        (new C123CatalogScraper('https://c123.com.br/willtec'))->scrape(null);
    }

    /**
     * Reproduz o Taranto: além do res.asp/resaj.asp normais do Willtec/Bel-Ar,
     * ele também carrega mRef (cross-reference) desde a primeira página.
     */
    public function test_includes_cross_references_when_the_storefront_embeds_mref(): void
    {
        Http::fake([
            'c123.com.br/taranto/res.asp' => Http::response($this->resAspBody(1, '06-AGO-2026a', [
                ['c' => 168, 'n' => '140000', 'd' => 'Jogo de Juntas', 't' => '140000.jpg', 'g' => 'Grupo', 's' => 'Sub'],
            ], crossReferences: [
                168 => ['121061PK', '10117EFS'],
            ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/taranto'))->scrape(null);

        $this->assertSame(['121061PK', '10117EFS'], $result->products[0]['conversoes']);
    }

    public function test_collects_cross_references_from_paginated_batches_too(): void
    {
        Http::fake([
            'c123.com.br/taranto/res.asp' => Http::response($this->resAspBody(2, '06-AGO-2026a', [
                ['c' => 1, 'n' => 'T01', 'd' => 'Peça Um', 't' => 't01.jpg', 'g' => 'G', 's' => 'S'],
            ]), 200, ['Set-Cookie' => 'ASPSESSIONID=abc123; path=/']),
            'c123.com.br/taranto/resaj.asp*' => Http::response($this->resajAspBody([
                ['idx' => 1, 'c' => 2, 'n' => 'T02', 'd' => 'Peça Dois', 't' => 't02.jpg', 'g' => 'G', 's' => 'S'],
            ], crossReferences: [
                2 => ['REF-T02'],
            ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/taranto'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
        $this->assertSame(['REF-T02'], $result->products[1]['conversoes']);
    }

    public function test_a_product_without_cross_references_gets_null_conversoes(): void
    {
        Http::fake([
            'c123.com.br/willtec/res.asp' => Http::response($this->resAspBody(1, '24-JUL-2026b', [
                ['c' => 1, 'n' => 'W01', 'd' => 'Peça Um', 't' => 'w01.jpg', 'g' => 'Grupo A', 's' => 'Sub A'],
            ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/willtec'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
    }

    public function test_works_against_a_different_configured_base_url(): void
    {
        Http::fake([
            'c123.com.br/bel-ar/res.asp' => Http::response($this->resAspBody(1, '24-JUL-2026b', [
                ['c' => 523, 'n' => '1.07.04.119.7', 'd' => 'Reparo Regulador', 't' => '1 07 04 119 7.jpg', 'g' => 'LINHA WABCO', 's' => 'Válvulas'],
            ]), 200),
        ]);

        $result = (new C123CatalogScraper('https://c123.com.br/bel-ar'))->scrape(null);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'c123.com.br/bel-ar/res.asp'));
        $this->assertSame('1.07.04.119.7', $result->products[0]['codigo']);
        $this->assertSame('https://c123.com.br/bel-ar/FotoProd/dcp/1 07 04 119 7.jpg', $result->products[0]['imagem_url']);
    }
}
