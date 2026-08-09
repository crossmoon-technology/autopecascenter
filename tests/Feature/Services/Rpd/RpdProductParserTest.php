<?php

namespace Tests\Feature\Services\Rpd;

use App\Services\Rpd\RpdProductParser;
use Tests\TestCase;

class RpdProductParserTest extends TestCase
{
    private function page(string $tables): string
    {
        return "<html><body>{$tables}</body></html>";
    }

    public function test_extracts_codigo_descricao_montadora_grupo_and_conversoes(): void
    {
        $html = $this->page(<<<'HTML'
            <div class="cabeitem" style="background-color: #b51319;"><div><p>CHEVROLET - BUCHAS</p></div></div>
            <table class="listapecas"><tr>
                <td class="lpecaimg"><img alt="10106" src="catalogo/am_e7b70bf557.jpg" /></td>
                <td class="lpecanum"><p class="listapecanum">10106<br /><span>95.463.563<br />96.852.643</span></p></td>
                <td class="lpecadescricao">REFIL SUPORTE TRASEIRO DO CAMBIO C/ TM E TA</td>
                <td class="lpecaaplicaco">COBALT<br />ONIX</td>
                <td class="lpecaano">11 / ...<br />13 / 19</td>
            </tr></table>
            HTML);

        $products = RpdProductParser::extractProducts($html);

        $this->assertCount(1, $products);
        $this->assertSame('10106', $products[0]['codigo']);
        $this->assertSame('REFIL SUPORTE TRASEIRO DO CAMBIO C/ TM E TA', $products[0]['descricao']);
        $this->assertSame('CHEVROLET', $products[0]['montadora']);
        $this->assertSame('BUCHAS', $products[0]['grupo']);
        $this->assertSame(['95463563', '96852643'], $products[0]['conversoes']);
        $this->assertSame('COBALT (11 / ...), ONIX (13 / 19)', $products[0]['aplicacao']);
        $this->assertSame('catalogo/am_e7b70bf557.jpg', $products[0]['imagem_src']);
    }

    /**
     * Reproduz um caso real: o <span> ao lado do código às vezes não é uma
     * conversão nenhuma, é só uma anotação entre parênteses (ex: peça vendida
     * "por lado") — não pode virar um código de equivalência fantasma.
     */
    public function test_ignores_a_parenthetical_annotation_that_is_not_a_real_code(): void
    {
        $html = $this->page(<<<'HTML'
            <div class="cabeitem" style="background-color: #b51319;"><div><p>CHEVROLET - KIT DE AMORTECEDOR</p></div></div>
            <table class="listapecas"><tr>
                <td class="lpecaimg"><img alt="1069/S" src="catalogo/am_4d1afc4fed.jpg" /></td>
                <td class="lpecanum"><p class="listapecanum">1069/S<br /><span>(1 LADO)</span></p></td>
                <td class="lpecadescricao">KIT DO AMORTECEDOR SIMPLES SUSPENSAO DIANTEIRA</td>
                <td class="lpecaaplicaco">CORSA</td>
                <td class="lpecaano">todos</td>
            </tr></table>
            HTML);

        $products = RpdProductParser::extractProducts($html);

        $this->assertSame('1069/S', $products[0]['codigo']);
        $this->assertNull($products[0]['conversoes']);
    }

    /**
     * Um código de conversão pode vir junto com a marca entre parênteses
     * (ex: "41033070 (IVECO)") no mesmo jeito visto no catálogo da Kaer — a
     * marca é descartada, o código em si é mantido.
     */
    public function test_extracts_a_conversao_written_with_a_brand_in_parentheses(): void
    {
        $html = $this->page(<<<'HTML'
            <div class="cabeitem"><div><p>IVECO - MOTOR</p></div></div>
            <table class="listapecas"><tr>
                <td class="lpecaimg"><img alt="4121R" src="x.jpg" /></td>
                <td class="lpecanum"><p class="listapecanum">4121R<br /><span>41033070 (IVECO)</span></p></td>
                <td class="lpecadescricao">COXIM</td>
                <td class="lpecaaplicaco">STRALIS</td>
                <td class="lpecaano">todos</td>
            </tr></table>
            HTML);

        $products = RpdProductParser::extractProducts($html);

        $this->assertSame(['41033070'], $products[0]['conversoes']);
    }

    public function test_returns_an_empty_array_when_there_are_no_tables(): void
    {
        $this->assertSame([], RpdProductParser::extractProducts($this->page('Nenhum resultado')));
    }

    public function test_skips_a_table_missing_the_codigo_cell(): void
    {
        $html = $this->page(<<<'HTML'
            <table class="listapecas"><tr>
                <td class="lpecadescricao">SEM CODIGO</td>
            </tr></table>
            HTML);

        $this->assertSame([], RpdProductParser::extractProducts($html));
    }
}
