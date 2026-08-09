<?php

namespace Tests\Feature\Services\Hella;

use App\Services\Hella\HellaProductParser;
use Tests\TestCase;

class HellaProductParserTest extends TestCase
{
    /**
     * Reproduz o formato real de um "RSC flight payload" do Next.js: linhas
     * numeradas com JSON embutido no meio de outro texto (referências "$L..."
     * a componentes React, etc.) — não é um JSON válido de ponta a ponta, só
     * pedaços dele são, por isso o parser localiza cada trecho por marcador
     * em vez de fazer json_decode() na resposta inteira.
     */
    private function listingChunk(array $produtos, int $total): string
    {
        $produtosJson = json_encode($produtos);

        return '5:["$","div",null,{"children":"$L6"}]'."\n"
            .'8:{"dehydratedAt":123,"state":{"data":{"tipo":"PRODUTO","produtos":'.$produtosJson.',"totalResultado":'.$total.'},"dataUpdateCount":1},"queryKey":["lista-resultado"]}'."\n"
            .'9:["$","div",null,{"children":"$L10"}]';
    }

    private function detailChunk(array $produto): string
    {
        $produtoJson = json_encode($produto);

        return '5:["$","div",null,{"children":"$L6"}]'."\n"
            .'8:{"dehydratedAt":123,"state":{"data":'.$produtoJson.',"dataUpdateCount":1},"queryKey":["detalhes-produto"]}'."\n"
            .'9:["$","div",null,{"children":"$L10"}]';
    }

    public function test_extracts_listing_codigo_produto_and_codigo(): void
    {
        $body = $this->listingChunk([
            ['CodigoProduto' => 1136, 'NumeroProduto' => 'HMP7421'],
            ['CodigoProduto' => 1101, 'NumeroProduto' => 'HMP6911'],
        ], total: 849);

        $this->assertSame([
            ['codigo_produto' => 1136, 'codigo' => 'HMP7421'],
            ['codigo_produto' => 1101, 'codigo' => 'HMP6911'],
        ], HellaProductParser::extractListing($body));
    }

    public function test_extracts_total(): void
    {
        $body = $this->listingChunk([], total: 849);

        $this->assertSame(849, HellaProductParser::extractTotal($body));
    }

    public function test_extracts_detail_fields_and_conversoes(): void
    {
        $body = $this->detailChunk([
            'CodigoProduto' => 1136,
            'NumeroProduto' => 'HMP7421',
            'DescricaoProduto' => 'MOTOR DE PARTIDA',
            'ArquivoFotoProduto' => '8ea015657421-right.jpg',
            'FabricantesAplicacao' => [
                [
                    'DescricaoFabricante' => 'VALTRA',
                    'Aplicacoes' => [
                        ['DescricaoAplicacao' => 'Tratores BM100', 'ComplementoAplicacao3_1' => '', 'ComplementoAplicacao3_2' => ''],
                    ],
                ],
            ],
            'ReferenciasCruzada' => [
                ['DescricaoFabricante' => 'DELCO', 'NumerosProduto' => [['NumeroProduto' => '8200564M']]],
                ['DescricaoFabricante' => 'SEG', 'NumerosProduto' => [['NumeroProduto' => 'T001.000.070']]],
            ],
        ]);

        $product = HellaProductParser::extractDetail($body);

        $this->assertSame('HMP7421', $product['codigo']);
        $this->assertSame('MOTOR DE PARTIDA', $product['descricao']);
        $this->assertSame('VALTRA Tratores BM100', $product['aplicacao']);
        // Só espaço é removido na normalização (mesma regra de Part::normalizeCode)
        // — pontos são preservados, já que "T001.000.070" é o código de verdade.
        $this->assertSame(['8200564M', 'T001.000.070'], $product['conversoes']);
        $this->assertSame('8ea015657421-right.jpg', $product['imagem_arquivo']);
    }

    public function test_returns_null_when_the_data_marker_is_missing(): void
    {
        $this->assertNull(HellaProductParser::extractDetail('5:["$","div",null,{}]'));
    }

    public function test_returns_empty_array_when_the_produtos_marker_is_missing(): void
    {
        $this->assertSame([], HellaProductParser::extractListing('5:["$","div",null,{}]'));
    }

    /**
     * O extrator de chaves balanceadas precisa ignorar chaves/colchetes que
     * aparecem dentro de strings (ex: numa descrição de produto) — senão para
     * de contar profundidade no lugar errado e corta o JSON pela metade.
     */
    public function test_handles_braces_inside_string_values_without_truncating(): void
    {
        $body = $this->detailChunk([
            'CodigoProduto' => 1,
            'NumeroProduto' => 'X1',
            'DescricaoProduto' => 'PEÇA COM "CHAVE {ESTRANHA}" NO NOME',
        ]);

        $product = HellaProductParser::extractDetail($body);

        $this->assertSame('PEÇA COM "CHAVE {ESTRANHA}" NO NOME', $product['descricao']);
    }
}
