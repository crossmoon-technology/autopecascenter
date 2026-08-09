<?php

namespace Tests\Feature\Services\ZM;

use App\Services\ZM\ZmProductParser;
use Tests\TestCase;

class ZmProductParserTest extends TestCase
{
    public function test_extracts_aplicacao_and_cross_reference_using_labels_not_position(): void
    {
        $tipoResponse = [
            'des_tipo' => 'Relés de Partida',
            'des_campo1' => 'Aplicação',
            'des_campo2' => 'Número Original',
            'des_campo3' => 'Voltagem',
            'produtos' => [
                [
                    'des_produto_lista_galeria' => 'ZM 1-291',
                    'des_produto_lista' => 'Relé auxiliar de partida ZM 1-291 24v',
                    'val_campo1' => 'DAF',
                    'val_campo2' => "0.333.006.026\n0.333.AD5.141",
                    'val_campo3' => '24V',
                    'des_caminho_imagem' => 'https://files.zm.com.br/1291.jpg',
                ],
            ],
        ];

        $products = ZmProductParser::extractProducts($tipoResponse);

        $this->assertCount(1, $products);
        $this->assertSame([
            'codigo' => 'ZM 1-291',
            'descricao' => 'Relé auxiliar de partida ZM 1-291 24v',
            'conversoes' => ['0.333.006.026', '0.333.AD5.141'],
            'fabricante' => 'DAF',
            'grupo' => 'Relés de Partida',
            'imagem_url' => 'https://files.zm.com.br/1291.jpg',
        ], $products[0]);
    }

    /**
     * Reproduz "Parafusos de Roda": campo2 aqui é "Dimensões", não
     * cross-reference nenhum — a posição val_campo2 sozinha não diz nada,
     * só o rótulo des_campo2 resolve o que cada campo significa.
     */
    public function test_a_field_labeled_something_else_is_not_mistaken_for_cross_reference(): void
    {
        $tipoResponse = [
            'des_tipo' => 'Parafusos de Roda',
            'des_campo1' => 'Aplicação',
            'des_campo2' => 'Dimensões',
            'des_campo3' => 'Acabamento',
            'produtos' => [
                [
                    'des_produto_lista_galeria' => 'ZM 2-100',
                    'des_produto_lista' => 'Parafuso de roda ZM 2-100',
                    'val_campo1' => 'UNIVERSAL',
                    'val_campo2' => 'M12x1,5',
                    'val_campo3' => 'Zincado',
                ],
            ],
        ];

        $products = ZmProductParser::extractProducts($tipoResponse);

        $this->assertNull($products[0]['conversoes']);
    }

    public function test_recognizes_the_alternate_cross_reference_label(): void
    {
        $tipoResponse = [
            'des_tipo' => 'Bieleta',
            'des_campo1' => 'Aplicação',
            'des_campo6' => 'Códigos Originais',
            'produtos' => [
                [
                    'des_produto_lista_galeria' => 'ZM 73-001',
                    'des_produto_lista' => 'Bieleta ZM 73-001',
                    'val_campo1' => 'FORD',
                    'val_campo6' => '1234567',
                ],
            ],
        ];

        $products = ZmProductParser::extractProducts($tipoResponse);

        $this->assertSame(['1234567'], $products[0]['conversoes']);
    }

    public function test_joins_multiple_aplicacao_values_with_a_comma(): void
    {
        $tipoResponse = [
            'des_tipo' => 'Relés de Partida',
            'des_campo1' => 'Aplicação',
            'produtos' => [
                [
                    'des_produto_lista_galeria' => 'ZM 1-407',
                    'des_produto_lista' => 'Relé ZM 1-407',
                    'val_campo1' => "CASE\nCUMMINS\nFORD",
                ],
            ],
        ];

        $products = ZmProductParser::extractProducts($tipoResponse);

        $this->assertSame('CASE, CUMMINS, FORD', $products[0]['fabricante']);
    }

    public function test_collapses_embedded_newlines_inside_the_description_into_spaces(): void
    {
        $tipoResponse = [
            'des_tipo' => 'Fixadores - Parafusos',
            'produtos' => [
                [
                    'des_produto_lista_galeria' => 'ZM 68.010.07',
                    'des_produto_lista' => "ZM 68.010.07 - Paraf. Sextavado Interno\nM10-1,50 MA x 45",
                ],
            ],
        ];

        $products = ZmProductParser::extractProducts($tipoResponse);

        $this->assertSame('ZM 68.010.07 - Paraf. Sextavado Interno M10-1,50 MA x 45', $products[0]['descricao']);
    }

    public function test_ignores_a_product_without_a_codigo(): void
    {
        $tipoResponse = [
            'des_tipo' => 'Ferramentas',
            'produtos' => [
                ['des_produto_lista' => 'Sem código'],
            ],
        ];

        $this->assertSame([], ZmProductParser::extractProducts($tipoResponse));
    }

    public function test_returns_an_empty_array_when_there_are_no_produtos(): void
    {
        $this->assertSame([], ZmProductParser::extractProducts(['des_tipo' => 'Ferramentas']));
    }

    public function test_returns_null_fabricante_conversoes_and_imagem_when_absent(): void
    {
        $tipoResponse = [
            'des_tipo' => 'Ferramentas',
            'produtos' => [
                ['des_produto_lista_galeria' => 'ZM 34-1', 'des_produto_lista' => 'Ferramenta ZM 34-1'],
            ],
        ];

        $products = ZmProductParser::extractProducts($tipoResponse);

        $this->assertNull($products[0]['fabricante']);
        $this->assertNull($products[0]['conversoes']);
        $this->assertNull($products[0]['imagem_url']);
    }
}
