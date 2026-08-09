<?php

namespace Tests\Feature\Services\BuscaNaRede;

use App\Services\BuscaNaRede\BuscaNaRedeProductParser;
use Tests\TestCase;

class BuscaNaRedeProductParserTest extends TestCase
{
    private function card(array $overrides = []): string
    {
        $attrs = array_merge([
            'codigo' => 'KTL1020',
            'titulo' => 'KIT&#x20;CORREIA&#x20;DE&#x20;DISTRIBUI&#x00C7;&#x00C3;O',
            'imagem' => 'https&#x3A;&#x2F;&#x2F;buscanarede.com.br&#x2F;cms&#x2F;x.jpg',
            'url' => 'https://buscanarede.com.br/linmaxbrasil/produto/108817/kit-correia-de-distribuicao-',
            'table' => '<tr><td class=""><b>GOL/ PARATI</b> - <info><span class="label">1.0 16V AT</span><span class="label">1.0 8V AT</span></info></td><td class="text-right">1996 / 2002</td></tr>',
        ], $overrides);

        return <<<HTML
            card-content card-top">
                <div class="b-items-title">
                    <h2>
                        <a class="green-b-l" href="{$attrs['url']}" rel="page">
                            Título visível
                        </a>
                    </h2>
                </div>
                <div class="table-marcas">
                    <table class="table table-hover">
                        <tbody>
                            {$attrs['table']}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-content">
                <a href="javascript:void(0)" id="comparar_108817"
                   data-item="108817" data-empresa="60940" data-action="add"
                   data-codigo="{$attrs['codigo']}" data-titulo="{$attrs['titulo']}"
                   data-catalogo="Linmax" data-imagem="{$attrs['imagem']}"></a>
            </div>
            HTML;
    }

    public function test_extracts_codigo_descricao_imagem_and_url(): void
    {
        $products = BuscaNaRedeProductParser::extractListing($this->card());

        $this->assertCount(1, $products);
        $this->assertSame('KTL1020', $products[0]['codigo']);
        $this->assertSame('KIT CORREIA DE DISTRIBUIÇÃO', $products[0]['descricao']);
        $this->assertSame('https://buscanarede.com.br/cms/x.jpg', $products[0]['imagem_url']);
        $this->assertSame('https://buscanarede.com.br/linmaxbrasil/produto/108817/kit-correia-de-distribuicao-', $products[0]['url_produto']);
    }

    public function test_extracts_aplicacao_from_the_cards_own_vehicle_table(): void
    {
        $products = BuscaNaRedeProductParser::extractListing($this->card());

        $this->assertSame('GOL/ PARATI - 1.0 16V AT 1.0 8V AT 1996 / 2002', $products[0]['aplicacao']);
    }

    /**
     * Reproduz um produto real com múltiplas montadoras/linhas na mesma
     * tabela de aplicação.
     */
    public function test_extracts_multiple_application_rows(): void
    {
        $table = '<tr><td>500</td><td>- 1.4 16V FIRE</td></tr>'
            .'<tr><td>PALIO / SIENA</td><td>- 1.3 16V FIRE</td></tr>';

        $products = BuscaNaRedeProductParser::extractListing($this->card(['table' => $table]));

        $this->assertSame('500 - 1.4 16V FIRE, PALIO / SIENA - 1.3 16V FIRE', $products[0]['aplicacao']);
    }

    public function test_ignores_a_card_without_a_codigo(): void
    {
        $body = str_replace('data-codigo="KTL1020"', 'data-codigo=""', $this->card());

        $this->assertSame([], BuscaNaRedeProductParser::extractListing($body));
    }

    public function test_extracts_multiple_cards(): void
    {
        $body = $this->card(['codigo' => 'KTL1020']).$this->card(['codigo' => 'KTL1019']);

        $products = BuscaNaRedeProductParser::extractListing($body);

        $this->assertSame(['KTL1020', 'KTL1019'], array_column($products, 'codigo'));
    }

    public function test_extracts_total_from_the_produtos_encontrados_counter(): void
    {
        $body = '<div class="me-auto"><b>37</b> <span>produtos encontrados</span></div>';

        $this->assertSame(37, BuscaNaRedeProductParser::extractTotal($body));
    }

    public function test_returns_null_total_when_the_counter_is_absent(): void
    {
        $this->assertNull(BuscaNaRedeProductParser::extractTotal('<html></html>'));
    }

    /**
     * Reproduz as respostas reais das duas abas AJAX: "equivalences" (marcas
     * do aftermarket) e "oem" (código original da montadora) — os códigos
     * das duas são mesclados numa lista só.
     */
    public function test_merges_codes_from_equivalences_and_oem_tabs(): void
    {
        $equivalences = '<div class="p-2"><b>DAYCO</b>: <span class="label">KTB255</span></div>'
            .'<div class="p-2"><b>GATES</b>: <span class="label">KS100</span></div>';
        $oem = '<div class="p-2"><b>Volkswagen</b> - <span class="label">030198119A</span><span class="label">6K0198001A</span></div>';

        $conversoes = BuscaNaRedeProductParser::extractConversoes($equivalences, $oem);

        $this->assertSame(['KTB255', 'KS100', '030198119A', '6K0198001A'], $conversoes);
    }

    public function test_returns_null_conversoes_when_both_tabs_are_empty(): void
    {
        $this->assertNull(BuscaNaRedeProductParser::extractConversoes('<div></div>', '<div></div>'));
    }
}
