<?php

namespace Tests\Feature\Services\MsMotorservice;

use App\Services\MsMotorservice\MsMotorserviceProductParser;
use Tests\TestCase;

class MsMotorserviceProductParserTest extends TestCase
{
    private function listingPage(array $items, ?string $totalDisplay = null): string
    {
        $json = json_encode(['data' => $items]);
        $total = $totalDisplay !== null
            ? '<strong id="cw-total-resultado-plural">'.$totalDisplay.'</strong>'
            : '<strong id="cw-total-resultado-plural"></strong>';

        return <<<HTML
            <html><body>
            {$total}
            <script id="__CW_DATA_LISTA_RESULTADO__" type="application/json">{$json}</script>
            </body></html>
            HTML;
    }

    private function detailPage(array $item): string
    {
        $json = json_encode(['data' => [$item]]);

        return <<<HTML
            <html><body>
            <script id="__CW_DATA_DETALHES_PRODUTO__" type="application/json">{$json}</script>
            </body></html>
            HTML;
    }

    public function test_extracts_listing_codigo_produto_and_codigo(): void
    {
        $html = $this->listingPage([
            ['CodigoProduto' => 4229, 'NumeroProduto' => '20060390601'],
            ['CodigoProduto' => 4739, 'NumeroProduto' => '20060711001'],
        ]);

        $this->assertSame([
            ['codigo_produto' => 4229, 'codigo' => '20060390601'],
            ['codigo_produto' => 4739, 'codigo' => '20060711001'],
        ], MsMotorserviceProductParser::extractListing($html));
    }

    public function test_extracts_total_from_the_brazilian_formatted_thousands_separator(): void
    {
        $html = $this->listingPage([], totalDisplay: '4.342');

        $this->assertSame(4342, MsMotorserviceProductParser::extractTotal($html));
    }

    public function test_extracts_detail_fields_and_conversoes(): void
    {
        $html = $this->detailPage([
            'NumeroProduto' => '20060390601',
            'DescricaoProduto' => 'Biela',
            'DescricaoFabricante' => 'Kolbenschmidt (KS)',
            'DescricaoGrupoProduto' => 'COMPONENTES DO MOTOR',
            'DescricaoSubGrupoProduto' => 'BIELA',
            'ArquivoFotoProduto' => '20060390601.jpg',
            'FabricantesAplicacao' => [
                [
                    'DescricaoFabricante' => 'MERCEDES-BENZ',
                    'Aplicacoes' => [
                        [
                            'DescricaoAplicacao' => '1215 C',
                            'ComplementoAplicacao3_1' => 'OM 904 LA',
                            'ComplementoAplicacao3_2' => '1999',
                            'ComplementoAplicacao3_3' => '2005',
                            'ComplementoAplicacao3_4' => 'DIESEL',
                        ],
                    ],
                ],
            ],
            'ReferenciasCruzada' => [
                [
                    'DescricaoFabricante' => 'MERCEDES-BENZ',
                    'NumerosProduto' => [
                        ['NumeroProduto' => '906 030 03 20'],
                        ['NumeroProduto' => 'A 906 030 03 20'],
                    ],
                ],
            ],
        ]);

        $product = MsMotorserviceProductParser::extractDetail($html);

        $this->assertSame('20060390601', $product['codigo']);
        $this->assertSame('Biela', $product['descricao']);
        $this->assertSame('Kolbenschmidt (KS)', $product['fabricante']);
        $this->assertSame('COMPONENTES DO MOTOR', $product['grupo']);
        $this->assertSame('BIELA', $product['subgrupo']);
        $this->assertSame('MERCEDES-BENZ 1215 C (OM 904 LA, 1999-2005, DIESEL)', $product['aplicacao']);
        // "906 030 03 20" e "A 906 030 03 20" são códigos DIFERENTES (o prefixo
        // "A" é parte do número da Mercedes-Benz, não formatação) — ambos preservados.
        $this->assertSame(['9060300320', 'A9060300320'], $product['conversoes']);
        $this->assertSame('20060390601.jpg', $product['imagem_arquivo']);
    }

    /**
     * Reproduz um caso real: quando início e fim de aplicação coincidem, não
     * faz sentido mostrar um intervalo "2005-2005" — só o ano único.
     */
    public function test_summarizes_a_single_year_application_without_a_range(): void
    {
        $html = $this->detailPage([
            'NumeroProduto' => 'X1',
            'DescricaoProduto' => 'Peça',
            'FabricantesAplicacao' => [
                [
                    'DescricaoFabricante' => 'SCANIA',
                    'Aplicacoes' => [
                        [
                            'DescricaoAplicacao' => 'R 440',
                            'ComplementoAplicacao3_1' => '-',
                            'ComplementoAplicacao3_2' => '2010',
                            'ComplementoAplicacao3_3' => '2010',
                            'ComplementoAplicacao3_4' => '-',
                        ],
                    ],
                ],
            ],
        ]);

        $product = MsMotorserviceProductParser::extractDetail($html);

        $this->assertSame('SCANIA R 440 (2010)', $product['aplicacao']);
    }

    public function test_returns_null_when_a_cross_reference_only_restates_the_primary_code(): void
    {
        $html = $this->detailPage([
            'NumeroProduto' => 'X1',
            'DescricaoProduto' => 'Peça',
            'ReferenciasCruzada' => [
                ['DescricaoFabricante' => 'X', 'NumerosProduto' => [['NumeroProduto' => 'x 1']]],
            ],
        ]);

        $this->assertNull(MsMotorserviceProductParser::extractDetail($html)['conversoes']);
    }

    public function test_returns_null_when_the_detail_script_is_missing(): void
    {
        $this->assertNull(MsMotorserviceProductParser::extractDetail('<html><body>sem dados</body></html>'));
    }

    /**
     * Reproduz o IKS: ao contrário do MS Motorservice, a listagem já embute
     * o mesmo formato completo (aplicação, cross-reference) do detalhe — sem
     * grupo/subgrupo, que simplesmente vêm null (mesmo tratamento de campo
     * ausente que qualquer outro campo faltante já recebe).
     */
    public function test_extracts_full_product_data_directly_from_the_listing_when_present(): void
    {
        $html = $this->listingPage([
            [
                'CodigoProduto' => 1228,
                'NumeroProduto' => '10101',
                'DescricaoProduto' => 'CABO DE ACELERADOR',
                'ArquivoFotoProduto' => '10101.jpg',
                'FabricantesAplicacao' => [
                    ['DescricaoFabricante' => 'MERCEDES-BENZ', 'Aplicacoes' => [['DescricaoAplicacao' => 'MB 180']]],
                ],
                'ReferenciasCruzada' => [
                    ['DescricaoFabricante' => 'ORIGINAL', 'NumerosProduto' => [['NumeroProduto' => '63.13.000.530']]],
                ],
            ],
        ]);

        $products = MsMotorserviceProductParser::extractFullListing($html);

        $this->assertCount(1, $products);
        $this->assertSame('10101', $products[0]['codigo']);
        $this->assertSame('CABO DE ACELERADOR', $products[0]['descricao']);
        $this->assertSame(['63.13.000.530'], $products[0]['conversoes']);
        $this->assertSame('MERCEDES-BENZ MB 180', $products[0]['aplicacao']);
        $this->assertNull($products[0]['grupo']);
        $this->assertNull($products[0]['subgrupo']);
        $this->assertSame('10101.jpg', $products[0]['imagem_arquivo']);
    }

    public function test_extract_full_listing_ignores_an_item_without_a_codigo(): void
    {
        $html = $this->listingPage([
            ['CodigoProduto' => 1, 'DescricaoProduto' => 'Sem número'],
        ]);

        $this->assertSame([], MsMotorserviceProductParser::extractFullListing($html));
    }
}
