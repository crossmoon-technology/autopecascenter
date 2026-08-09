<?php

namespace Tests\Feature\Services\MgPecasAutomotivas;

use App\Services\MgPecasAutomotivas\MgPecasAutomotivasProductParser;
use Tests\TestCase;

class MgPecasAutomotivasProductParserTest extends TestCase
{
    private function itemBlock(array $data): string
    {
        $json = json_encode(array_merge([
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
        ], $data));

        return "<script type=\"application/ld+json\" data-component='structured-data.item'>{$json}</script>";
    }

    public function test_extracts_listing_codigo_imagem_and_url(): void
    {
        $body = $this->itemBlock([
            'name' => 'MG001',
            'image' => 'https://cdn/mg001.webp',
            'offers' => ['url' => 'https://mgpecasautomotivas.com.br/produtos/mg001/'],
        ]);

        $listings = MgPecasAutomotivasProductParser::extractListing($body);

        $this->assertSame([
            'codigo' => 'MG001',
            'imagem_url' => 'https://cdn/mg001.webp',
            'url_produto' => 'https://mgpecasautomotivas.com.br/produtos/mg001/',
        ], $listings[0]);
    }

    public function test_ignores_an_item_block_without_a_name(): void
    {
        $body = $this->itemBlock(['image' => 'https://cdn/x.webp']);

        $this->assertSame([], MgPecasAutomotivasProductParser::extractListing($body));
    }

    public function test_extracts_the_total_from_the_ls_products_count_variable(): void
    {
        $body = 'LS.productsCount = 1366;';

        $this->assertSame(1366, MgPecasAutomotivasProductParser::extractTotal($body));
    }

    public function test_returns_null_total_when_the_variable_is_absent(): void
    {
        $this->assertNull(MgPecasAutomotivasProductParser::extractTotal('<html></html>'));
    }

    private function detailPage(array $breadcrumbNames, string $userContentHtml): string
    {
        $items = collect($breadcrumbNames)->map(fn (string $name, int $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $name,
        ])->values()->all();

        $json = json_encode([
            '@context' => 'https://schema.org/',
            '@type' => 'WebPage',
            'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => $items],
        ]);

        return <<<HTML
            <script type="application/ld+json" data-component='structured-data.page'>{$json}</script>
            <div class="user-content font-small mb-4">{$userContentHtml}</div>
            HTML;
    }

    /**
     * Reproduz um produto real (MG001): descrição, "Nº Original" com um
     * único código, e "Aplicação" com duas linhas de veículo.
     */
    public function test_extracts_descricao_conversoes_and_aplicacao_from_a_real_shaped_product(): void
    {
        $body = $this->detailPage(
            ['Início', 'MANG. FILTRO DE AR', 'FIAT', 'MG001'],
            '<p><span><span>MANGUEIRA FILTRO DE AR</span></span></p>'
            .'<p><span><span>Nº Original</span>: </span><span>46445723/46448515</span></p>'
            .'<p><span>Aplicação</span><span>: </span></p>'
            .'<p><span>PALIO 1.0 8V 1996/2000 </span></p>'
            .'<p><span>SIENA 1.0 8V 1996/2000</span></p>'
        );

        $result = MgPecasAutomotivasProductParser::extractDetail($body);

        $this->assertSame('MANGUEIRA FILTRO DE AR', $result['descricao']);
        $this->assertSame('MANG. FILTRO DE AR', $result['grupo']);
        $this->assertSame('FIAT', $result['fabricante']);
        $this->assertSame(['46445723', '46448515'], $result['conversoes']);
        $this->assertSame('PALIO 1.0 8V 1996/2000, SIENA 1.0 8V 1996/2000', $result['aplicacao']);
    }

    /**
     * Reproduz outro produto real (MG003): três códigos "Nº Original"
     * separados por " / " (com espaços, não só "/").
     */
    public function test_splits_multiple_original_codes_with_spaced_slashes(): void
    {
        $body = $this->detailPage(
            ['Início', 'MANG. FILTRO DE AR', 'FIAT', 'MG003'],
            '<p><span>MANGUEIRA FILTRO DE AR</span></p>'
            .'<p><span>Nº Original</span>: <span>50017371 / 50018001 / 50018278</span></p>'
            .'<p><span>Aplicação</span>: </p>'
            .'<p><span>FIORINO 1.3 FIRE 8V 2003/2007</span></p>'
        );

        $result = MgPecasAutomotivasProductParser::extractDetail($body);

        $this->assertSame(['50017371', '50018001', '50018278'], $result['conversoes']);
    }

    public function test_returns_nulls_when_the_user_content_block_is_missing(): void
    {
        $body = $this->detailPage(['Início', 'GRUPO', 'MARCA', 'MG999'], '');
        $body = str_replace('<div class="user-content font-small mb-4"></div>', '', $body);

        $result = MgPecasAutomotivasProductParser::extractDetail($body);

        $this->assertNull($result['descricao']);
        $this->assertNull($result['conversoes']);
        $this->assertNull($result['aplicacao']);
    }

    public function test_returns_null_aplicacao_when_there_is_no_vehicle_listed(): void
    {
        $body = $this->detailPage(
            ['Início', 'GRUPO', 'MARCA', 'MG999'],
            '<p><span>PEÇA GENÉRICA</span></p><p><span>Aplicação</span>: </p>'
        );

        $this->assertNull(MgPecasAutomotivasProductParser::extractDetail($body)['aplicacao']);
    }
}
