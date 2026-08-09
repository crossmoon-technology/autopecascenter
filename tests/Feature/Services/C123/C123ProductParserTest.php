<?php

namespace Tests\Feature\Services\C123;

use App\Services\C123\C123ProductParser;
use Tests\TestCase;

class C123ProductParserTest extends TestCase
{
    public function test_extracts_multiple_products(): void
    {
        $body = <<<'JS'
            function fP(){this.c=0;this.n='';this.d='';this.i='';this.t='';this.qd='';this.pd='';this.ld='';this.g='';this.s='';this.p=0;}var mPrd=new Array();mPrd[0]=new fP();with(mPrd[0]){c=11797;n='W02.028C';d='Manômetro Mecânico';i='1';t='W02.028C.jpg';g='Tratores';s='Sub A';p='0';}mPrd[1]=new fP();with(mPrd[1]){c=11798;n='W02.028P';d='Manômetro Pneumático';i='1';t='W02.028P.jpg';g='Tratores';s='Sub B';p='0';}
            JS;

        $products = C123ProductParser::extractProducts($body);

        $this->assertCount(2, $products);
        $this->assertSame([
            'codigoInterno' => '11797',
            'codigo' => 'W02.028C',
            'descricao' => 'Manômetro Mecânico',
            'imagem' => 'W02.028C.jpg',
            'grupo' => 'Tratores',
            'subgrupo' => 'Sub A',
        ], $products[0]);
        $this->assertSame('W02.028P', $products[1]['codigo']);
        $this->assertSame('Sub B', $products[1]['subgrupo']);
    }

    public function test_returns_an_empty_array_when_there_are_no_products(): void
    {
        $this->assertSame([], C123ProductParser::extractProducts('var mTotPrd=0;'));
    }

    /**
     * Reproduz um caso real do Willtec (categoria "Esportivo"): sem campo t=
     * (sem imagem) e com um campo ld= extra no final, em vez da sequência
     * c;n;d;i;t;g;s;p de sempre — uma página inteira composta só desses itens
     * fazia o regex antigo (posicional/estrito) não bater com nenhum, o que
     * o scraper interpretava como "não há mais páginas" e parava ali,
     * centenas de páginas antes do fim real do catálogo.
     */
    public function test_extracts_products_missing_the_image_field_with_extra_trailing_fields(): void
    {
        $body = "mPrd[0]=new fP();with(mPrd[0]){c=17055;n='WS03.024A';d='Manômetro Duplo';i='0001';g='Esportivo';s='PRETO SUPER RACING';p='0';ld='';}";

        $products = C123ProductParser::extractProducts($body);

        $this->assertCount(1, $products);
        $this->assertSame([
            'codigoInterno' => '17055',
            'codigo' => 'WS03.024A',
            'descricao' => 'Manômetro Duplo',
            'imagem' => '',
            'grupo' => 'Esportivo',
            'subgrupo' => 'PRETO SUPER RACING',
        ], $products[0]);
    }

    public function test_ignores_a_block_without_a_c_field(): void
    {
        $body = "mPrd[0]=new fP();with(mPrd[0]){n='X';d='Y';}";

        $this->assertSame([], C123ProductParser::extractProducts($body));
    }

    /**
     * Reproduz o formato real da Fania: mRef[N] é indexado pelo MESMO N do
     * codigoInterno (c=) do produto correspondente em mPrd — confirmado ao
     * vivo comparando os dois. f[] carrega a marca (com um dígito de estilo
     * na frente, ex: "0Original") que não interessa aqui, só os códigos em n[].
     */
    public function test_extracts_cross_references_keyed_by_codigo_interno(): void
    {
        $body = "mRef[442]=new fR();with(mRef[442]){f=new Array();n=new Array();f[0]='0Original';n[0]='7333991';f[1]='0Efrari';n[1]='GM229';}"
            ."mRef[748]=new fR();with(mRef[748]){f=new Array();n=new Array();f[0]='0Original';n[0]='8975863';}";

        $this->assertSame([
            '442' => ['7333991', 'GM229'],
            '748' => ['8975863'],
        ], C123ProductParser::extractCrossReferences($body));
    }

    public function test_returns_an_empty_array_when_there_are_no_cross_references(): void
    {
        $this->assertSame([], C123ProductParser::extractCrossReferences('mTotPrd=0;'));
    }
}
