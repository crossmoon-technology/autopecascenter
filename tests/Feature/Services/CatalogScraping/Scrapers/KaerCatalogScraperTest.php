<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\KaerCatalogScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KaerCatalogScraperTest extends TestCase
{
    private function searchPage(array $documents): string
    {
        $warmupData = json_encode([
            'appsWarmupData' => [
                '1484cb44-49cd-5b39-9681-75188ab429de' => [
                    'search:SearchResponse' => ['documents' => $documents],
                ],
            ],
        ]);

        return "<html><head><script type=\"application/json\" id=\"wix-warmup-data\">{$warmupData}</script></head></html>";
    }

    private function detailPage(string $urlPart, string $name, string $description, array $media): string
    {
        $warmupData = json_encode([
            'appsWarmupData' => [
                '1380b703-ce81-ff05-f115-39571d94dfcd' => [
                    "productPage_BRL_{$urlPart}" => [
                        'catalog' => [
                            'product' => [
                                'name' => $name,
                                'description' => $description,
                                'media' => $media,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return "<html><head><script type=\"application/json\" id=\"wix-warmup-data\">{$warmupData}</script></head></html>";
    }

    public function test_scrapes_a_single_page_of_listings_and_their_detail_pages(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => '257210 - 606 - ESPAÇADOR', 'relativeUrl' => '/product-page/257210-606-espacador'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/257210-606-espacador' => Http::response($this->detailPage(
                '257210-606-espacador',
                '257210 - 606 - ESPAÇADOR',
                '<p>CÓDIGOS: 257210&nbsp;(SCANIA)<br>APLICAÇÃO: Scania - LK140/41/42</p>',
                [['fullUrl' => 'https://static.wixstatic.com/media/img1.webp']]
            ), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('257210', $result->products[0]['codigo']);
        $this->assertSame('ESPAÇADOR', $result->products[0]['descricao']);
        $this->assertSame('CÓDIGOS: 257210 (SCANIA) APLICAÇÃO: Scania - LK140/41/42', $result->products[0]['detalhes']);
        $this->assertSame('https://static.wixstatic.com/media/img1.webp', $result->products[0]['imagem_url']);
        // Só o código principal aparece em "CÓDIGOS:" aqui — sem conversão nenhuma.
        $this->assertNull($result->products[0]['conversoes']);
        $this->assertNotNull($result->source_version);
    }

    /**
     * "CÓDIGOS:" às vezes traz o código principal E códigos equivalentes de
     * outras marcas (entre parênteses) — esses extras são as conversões da
     * peça, essenciais pro pareamento de equivalências entre catálogos.
     */
    public function test_extracts_conversoes_from_additional_codes_in_the_description(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => '41210621R - 712 - KIT REPARO', 'relativeUrl' => '/product-page/41210621r'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/41210621r' => Http::response($this->detailPage(
                '41210621r',
                '41210621R - 712 - KIT REPARO',
                '<p>CÓDIGOS: 41210621R - 41033070 (IVECO)<br>APLICAÇÃO: VEÍCULOS IVECO STRALIS</p>',
                []
            ), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertSame(['41033070'], $result->products[0]['conversoes']);
    }

    public function test_extracts_multiple_conversoes_without_a_brand_in_parentheses(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => '2S2711118 - 112 - COIFA', 'relativeUrl' => '/product-page/2s2711118'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/2s2711118' => Http::response($this->detailPage(
                '2s2711118',
                '2S2711118 - 112 - COIFA',
                '<p>CÓDIGOS: 2S2711118 2S2711118F1NN 2S2971771B<br>APLICAÇÃO: VW</p>',
                []
            ), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertSame(['2S2711118F1NN', '2S2971771B'], $result->products[0]['conversoes']);
    }

    /**
     * Reproduz um caso real: o site repete o código principal com um espaço
     * a mais no meio ("8141585 X" pro código "8141585X") em vez de trazer
     * uma conversão de verdade — não deve virar um código extra fantasma.
     */
    public function test_returns_null_when_the_codigos_segment_just_restates_the_primary_code_with_different_spacing(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => '8141585X - 1 - PEÇA', 'relativeUrl' => '/product-page/8141585x'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/8141585x' => Http::response($this->detailPage(
                '8141585x',
                '8141585X - 1 - PEÇA',
                '<p>CÓDIGOS: 8141585 X<br>APLICAÇÃO: Mercedes-Benz</p>',
                []
            ), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
    }

    /**
     * Reproduz outro caso real: variantes separadas por "|" (em vez de espaço),
     * uma delas repetindo o código principal.
     */
    public function test_extracts_conversoes_separated_by_pipes(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => '2W0141335 - 1 - PEÇA', 'relativeUrl' => '/product-page/2w0141335'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/2w0141335' => Http::response($this->detailPage(
                '2w0141335',
                '2W0141335 - 1 - PEÇA',
                '<p>CÓDIGOS: 2W0141335 |0501324061 |9702600098<br>APLICAÇÃO: VW</p>',
                []
            ), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertSame(['0501324061', '9702600098'], $result->products[0]['conversoes']);
    }

    /**
     * Reproduz um caso real da própria Kaer: o texto bagunçado com variantes do
     * código em formatos diferentes ("2S2/711/118", "2S2 711 118", etc.) fica
     * dentro de APLICAÇÃO (aplicação de veículo), não em CÓDIGOS — então não
     * deve ser minerado como conversão, mesmo contendo dígitos parecidos com
     * código de peça. Só o que vem antes de "APLICAÇÃO:" conta.
     */
    public function test_ignores_slash_and_space_grouped_codes_that_live_in_the_aplicacao_section(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => '2S2711118 - 112 - COIFA', 'relativeUrl' => '/product-page/2s2711118'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/2s2711118' => Http::response($this->detailPage(
                '2s2711118',
                '2S2711118 - 112 - COIFA',
                '<p>CÓDIGOS: 2S2711118 2S2711118F1NN 2S2971771B'
                    .'APLICAÇÃO: VW 2S2711118 // 2S2711118999 // 2S2/711/118 // 2S2/711/118/999 // 2S2 711 118'
                    .'VW CONSTELLATION TODOS 2006/2018 /// 13180 / 15180 / 17250'
                    .'DADOS TÉCNICOS: CONTÉM 1 UNIDADE COM MICRO SWITCH</p>',
                []
            ), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertSame(['2S2711118F1NN', '2S2971771B'], $result->products[0]['conversoes']);
    }

    /**
     * Reproduz uma seção "CÓDIGOS:" com texto livre descrevendo o kit — palavras
     * sem dígito não podem virar código de conversão.
     */
    public function test_ignores_free_text_noise_in_the_codigos_segment(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => '9702600098 - 1 - KIT', 'relativeUrl' => '/product-page/9702600098'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/9702600098' => Http::response($this->detailPage(
                '9702600098',
                '9702600098 - 1 - KIT',
                '<p>CÓDIGOS: 9702600098 KIT COMPLETO REPARO DA CAIXA<br>APLICAÇÃO: VW</p>',
                []
            ), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertNull($result->products[0]['conversoes']);
    }

    public function test_paginates_search_results_until_an_empty_page(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => 'A1 - 1 - Peça Um', 'relativeUrl' => '/product-page/a1'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p2', 'title' => 'A2 - 2 - Peça Dois', 'relativeUrl' => '/product-page/a2'],
            ]), 200),
            'kaerbrasil.com/search*page=3*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/a1' => Http::response($this->detailPage('a1', 'A1 - 1 - Peça Um', 'desc', []), 200),
            'kaerbrasil.com/product-page/a2' => Http::response($this->detailPage('a2', 'A2 - 2 - Peça Dois', 'desc', []), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertCount(2, $result->products);
        $this->assertSame(['A1', 'A2'], array_column($result->products, 'codigo'));
    }

    public function test_skips_entirely_when_the_listing_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => 'A1 - 1 - Peça Um', 'relativeUrl' => '/product-page/a1'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/a1' => Http::response($this->detailPage('a1', 'A1 - 1 - Peça Um', 'desc', []), 200),
        ]);

        $scraper = new KaerCatalogScraper('https://www.kaerbrasil.com');
        $first = $scraper->scrape(null);

        // 2 páginas de listagem + 1 detalhe (não há como calcular o fingerprint
        // sem antes paginar a listagem, diferente do marcador da C123).
        Http::assertSentCount(3);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
        // +2 páginas de listagem de novo pra recalcular o fingerprint, mas
        // nenhuma chamada de detalhe — pulou antes de buscar qualquer peça.
        Http::assertSentCount(5);
    }

    /**
     * Reproduz uma falha real: um erro transitório de conexão (SSL "unexpected
     * eof") na segunda página de busca do Kaer derrubou a raspagem inteira —
     * ->retry() em cada requisição evita isso sem precisar reiniciar do zero.
     */
    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->searchPage([
                    ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => 'A1 - 1 - Peça Um', 'relativeUrl' => '/product-page/a1'],
                ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/a1' => Http::response($this->detailPage('a1', 'A1 - 1 - Peça Um', 'desc', []), 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    public function test_skips_a_listing_when_its_detail_page_cannot_be_parsed(): void
    {
        Http::fake([
            'kaerbrasil.com/search*page=1*' => Http::response($this->searchPage([
                ['documentType' => 'public/stores/products', 'id' => 'p1', 'title' => 'A1 - 1 - Peça Um', 'relativeUrl' => '/product-page/a1'],
            ]), 200),
            'kaerbrasil.com/search*page=2*' => Http::response($this->searchPage([]), 200),
            'kaerbrasil.com/product-page/a1' => Http::response('<html>sem warmup data</html>', 200),
        ]);

        $result = (new KaerCatalogScraper('https://www.kaerbrasil.com'))->scrape(null);

        $this->assertCount(0, $result->products);
    }
}
