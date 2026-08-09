<?php

namespace Tests\Feature\Services\NatIndustria;

use App\Services\NatIndustria\NatIndustriaProductParser;
use Tests\TestCase;

class NatIndustriaProductParserTest extends TestCase
{
    public function test_extracts_sitemap_urls_and_lastmod(): void
    {
        $xml = '<urlset><url><loc>https://natindustria.com.br/produto/x/</loc><lastmod>2021-01-19T10:46:13+00:00</lastmod></url>'
            .'<url><loc>https://natindustria.com.br/produto/y/</loc><lastmod>2023-07-21T14:31:50+00:00</lastmod></url></urlset>';

        $entries = NatIndustriaProductParser::extractSitemap($xml);

        $this->assertSame([
            ['url' => 'https://natindustria.com.br/produto/x/', 'lastmod' => '2021-01-19T10:46:13+00:00'],
            ['url' => 'https://natindustria.com.br/produto/y/', 'lastmod' => '2023-07-21T14:31:50+00:00'],
        ], $entries);
    }

    public function test_returns_empty_array_for_a_sitemap_with_no_urls(): void
    {
        $this->assertSame([], NatIndustriaProductParser::extractSitemap('<urlset></urlset>'));
    }

    private function detailPage(string $title, string $contentHtml, string $image = 'https://cdn/x.jpg'): string
    {
        return <<<HTML
            <img id="largeImage" class="product-image" src="{$image}">
            <h1 class="single-product_title">{$title}</h1>
            <div class="single-product_content">{$contentHtml}</div>
            <div class="widget-shared"></div>
            HTML;
    }

    private function swatchDiv(string $caption): string
    {
        return <<<HTML
            <div style="border-radius: 25px;">
                <div class="bolinha_de_cor floatleft"></div>
                <p style="float: left;">{$caption}</p>
            </div>
            HTML;
    }

    /**
     * Reproduz o produto real "coifa de câmbio - 400497": um único swatch,
     * com o código ANTES do hífen (não depois, ao contrário do outro caso
     * real abaixo).
     */
    public function test_single_variant_with_code_before_the_dash_in_the_swatch(): void
    {
        $body = $this->detailPage(
            'COIFA DE CÂMBIO - 400497',
            'AGILE 2010/2014 - MONTANA 2011/2021'.$this->swatchDiv('400497 - GRAFITE')
        );

        $products = NatIndustriaProductParser::extractDetail($body);

        $this->assertCount(1, $products);
        $this->assertSame('400497', $products[0]['codigo']);
        $this->assertSame('COIFA DE CÂMBIO - GRAFITE', $products[0]['descricao']);
        $this->assertSame('AGILE 2010/2014 - MONTANA 2011/2021', $products[0]['aplicacao']);
        $this->assertSame('https://cdn/x.jpg', $products[0]['imagem_url']);
    }

    /**
     * Reproduz o produto real "Apoio de Braço Golf": SEM código no título,
     * 3 swatches, cada um com seu PRÓPRIO código (produtos diferentes de
     * verdade) — e aqui o código vem DEPOIS do texto da cor.
     */
    public function test_multi_variant_with_a_distinct_code_per_swatch(): void
    {
        $body = $this->detailPage(
            'Apoio de Braço Golf',
            'Golf 99/13'
            .$this->swatchDiv('Preto c/Linha Preta - 100700')
            .$this->swatchDiv('Cinza c/Linha Cinza - 100701')
            .$this->swatchDiv('Grafite c/Linha Grafite - 100702')
        );

        $products = NatIndustriaProductParser::extractDetail($body);

        $this->assertSame(['100700', '100701', '100702'], array_column($products, 'codigo'));
        $this->assertSame('Apoio de Braço Golf - Preto c/Linha Preta', $products[0]['descricao']);
        $this->assertSame('Apoio de Braço Golf - Cinza c/Linha Cinza', $products[1]['descricao']);
        $this->assertSame('Golf 99/13', $products[0]['aplicacao']);
    }

    /**
     * Reproduz o produto real "coifa com manopla - 100169A": código só no
     * título, o único swatch não tem nenhum código — precisa cair pro
     * fallback do título.
     */
    public function test_falls_back_to_the_title_code_when_the_only_swatch_has_none(): void
    {
        $body = $this->detailPage(
            'coifa com manopla - 100169A',
            'Gol/Voyage/Parati 81/94'.$this->swatchDiv('Grafite c/Linha Grafite')
        );

        $products = NatIndustriaProductParser::extractDetail($body);

        $this->assertCount(1, $products);
        $this->assertSame('100169A', $products[0]['codigo']);
        $this->assertSame('coifa com manopla - Grafite c/Linha Grafite', $products[0]['descricao']);
    }

    public function test_returns_empty_array_when_there_is_no_title(): void
    {
        $this->assertSame([], NatIndustriaProductParser::extractDetail('<html>sem produto</html>'));
    }

    public function test_returns_empty_array_when_neither_title_nor_swatch_has_a_code(): void
    {
        $body = $this->detailPage('Apoio de Braço', 'Golf 99/13'.$this->swatchDiv('Preto'));

        $this->assertSame([], NatIndustriaProductParser::extractDetail($body));
    }

    public function test_returns_null_aplicacao_when_the_content_block_is_empty(): void
    {
        $body = $this->detailPage('difusor de ar - 200605', $this->swatchDiv('200605 - Preto'));

        $this->assertNull(NatIndustriaProductParser::extractDetail($body)[0]['aplicacao']);
    }
}
