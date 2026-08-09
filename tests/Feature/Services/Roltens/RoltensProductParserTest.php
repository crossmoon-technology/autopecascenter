<?php

namespace Tests\Feature\Services\Roltens;

use App\Services\Roltens\RoltensProductParser;
use Tests\TestCase;

class RoltensProductParserTest extends TestCase
{
    public function test_extracts_listings_and_total_from_the_listagem_json(): void
    {
        $json = json_encode([
            'total' => 1306,
            'rows' => [
                [
                    'codigo' => 'RT5205',
                    'descricao' => 'Polia do Virabrequim',
                    'grupo' => 'Polia do Virabrequim',
                    'foto' => 'https://roltens.com.br/fotos/rt5205.jpg,https://roltens.com.br/fotos/rt5205.jpg',
                    'saibamais' => 'Saiba mais,https://roltens.com.br/produto/RT5205',
                ],
            ],
        ]);

        $result = RoltensProductParser::extractListing($json);

        $this->assertSame(1306, $result['total']);
        $this->assertSame([
            'codigo' => 'RT5205',
            'descricao' => 'Polia do Virabrequim',
            'grupo' => 'Polia do Virabrequim',
            'imagem_url' => 'https://roltens.com.br/fotos/rt5205.jpg',
            'url_produto' => 'https://roltens.com.br/produto/RT5205',
        ], $result['listings'][0]);
    }

    public function test_ignores_a_row_without_a_codigo(): void
    {
        $json = json_encode(['total' => 1, 'rows' => [['descricao' => 'Sem código']]]);

        $this->assertSame([], RoltensProductParser::extractListing($json)['listings']);
    }

    public function test_returns_empty_listings_and_null_total_for_malformed_json(): void
    {
        $result = RoltensProductParser::extractListing('não é json');

        $this->assertSame([], $result['listings']);
        $this->assertNull($result['total']);
    }

    /**
     * Reproduz um produto real (RT5202) com múltiplos "Cód. original" —
     * cada um é o próprio texto de um <li>, não uma lista aninhada.
     */
    public function test_extracts_multiple_cod_original_entries(): void
    {
        $html = <<<'HTML'
            <h5>Referências: </h5>
            <ul class="list-texts text-left">
                <li><b>Cód. original:</b> 0515K1</li>
                <li><b>Cód. original:</b> 0515N0</li>
                <li><b>Cód. original:</b> 9636924480</li>
            </ul>
            HTML;

        $result = RoltensProductParser::extractDetail($html);

        $this->assertSame(['0515K1', '0515N0', '9636924480'], $result['conversoes']);
    }

    public function test_returns_null_conversoes_when_there_is_no_cod_original(): void
    {
        $html = <<<'HTML'
            <h5>Referências: </h5>
            <ul class="list-texts text-left">
            </ul>
            HTML;

        $this->assertNull(RoltensProductParser::extractDetail($html)['conversoes']);
    }

    public function test_extracts_aplicacao_items_joined_by_comma(): void
    {
        $html = <<<'HTML'
            <h5>Aplicação:</h5>
            <ul class="list-texts text-left">
                <li>Fiat Uno 1.0</li>
                <li>Fiat Palio 1.0</li>
            </ul>
            HTML;

        $this->assertSame('Fiat Uno 1.0, Fiat Palio 1.0', RoltensProductParser::extractDetail($html)['aplicacao']);
    }

    public function test_returns_null_aplicacao_when_the_list_is_empty(): void
    {
        $html = <<<'HTML'
            <h5>Aplicação:</h5>
            <ul class="list-texts text-left">
            </ul>
            HTML;

        $this->assertNull(RoltensProductParser::extractDetail($html)['aplicacao']);
    }
}
