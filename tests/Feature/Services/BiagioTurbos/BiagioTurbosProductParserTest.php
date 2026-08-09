<?php

namespace Tests\Feature\Services\BiagioTurbos;

use App\Services\BiagioTurbos\BiagioTurbosProductParser;
use Tests\TestCase;

class BiagioTurbosProductParserTest extends TestCase
{
    private function detailJson(array $overrides = []): string
    {
        $data = array_merge([
            'turbo' => [
                'partNumber' => '01030001',
                'modelo' => 'BBV145AT',
                'linha' => ['id' => 4, 'nome' => 'BBV'],
                'arquivosLeves' => [
                    ['id' => 843, 'tipo' => 'foto', 'url' => '/turbos/arquivos/843'],
                    ['id' => 844, 'tipo' => 'medidasMontagem', 'url' => '/turbos/arquivos/844'],
                ],
                'ligacoes' => [
                    [
                        'motor' => ['nome' => 'F1C'],
                        'veiculo' => ['modelo' => 'DAILY 35S14', 'montadora' => ['nome' => 'IVECO']],
                    ],
                    [
                        'motor' => ['nome' => 'F1C'],
                        'veiculo' => ['modelo' => 'DAILY 45S14', 'montadora' => ['nome' => 'IVECO']],
                    ],
                ],
            ],
            'tiposReferencias' => [
                ['tipo' => 'O.E.M.', 'referencias' => [
                    ['referencia' => '49189-02910'],
                    ['referencia' => '504092197'],
                ]],
                ['tipo' => 'P/N KKK', 'referencias' => [
                    ['referencia' => '49189-02911'],
                ]],
            ],
        ], $overrides);

        return json_encode($data);
    }

    public function test_extracts_codigo_descricao_grupo_aplicacao_conversoes_and_imagem(): void
    {
        $product = BiagioTurbosProductParser::extractDetail($this->detailJson(), 'https://catalogo.biagioturbos.com.br');

        $this->assertSame('01030001', $product['codigo']);
        $this->assertSame('BBV145AT', $product['descricao']);
        $this->assertSame('BBV', $product['grupo']);
        $this->assertSame('IVECO DAILY 35S14 (F1C), IVECO DAILY 45S14 (F1C)', $product['aplicacao']);
        $this->assertSame(['49189-02910', '504092197', '49189-02911'], $product['conversoes']);
        $this->assertSame('https://catalogo.biagioturbos.com.br/api/turbos/arquivos/843', $product['imagem_url']);
    }

    public function test_returns_null_when_the_part_number_is_missing(): void
    {
        $this->assertNull(BiagioTurbosProductParser::extractDetail('{"turbo":{}}', 'https://x'));
    }

    public function test_returns_null_when_the_json_is_invalid(): void
    {
        $this->assertNull(BiagioTurbosProductParser::extractDetail('not json', 'https://x'));
    }

    public function test_deduplicates_identical_aplicacao_lines_from_repeated_vehicle_links(): void
    {
        $product = BiagioTurbosProductParser::extractDetail($this->detailJson([
            'turbo' => array_merge($this->baseTurbo(), [
                'ligacoes' => [
                    ['motor' => ['nome' => 'F1C'], 'veiculo' => ['modelo' => 'DAILY 35S14', 'montadora' => ['nome' => 'IVECO']]],
                    ['motor' => ['nome' => 'F1C'], 'veiculo' => ['modelo' => 'DAILY 35S14', 'montadora' => ['nome' => 'IVECO']]],
                ],
            ]),
        ]), 'https://x');

        $this->assertSame('IVECO DAILY 35S14 (F1C)', $product['aplicacao']);
    }

    public function test_returns_null_aplicacao_when_there_are_no_ligacoes(): void
    {
        $product = BiagioTurbosProductParser::extractDetail($this->detailJson([
            'turbo' => array_merge($this->baseTurbo(), ['ligacoes' => []]),
        ]), 'https://x');

        $this->assertNull($product['aplicacao']);
    }

    public function test_returns_null_conversoes_when_there_are_no_tipos_referencias(): void
    {
        $product = BiagioTurbosProductParser::extractDetail($this->detailJson(['tiposReferencias' => []]), 'https://x');

        $this->assertNull($product['conversoes']);
    }

    /**
     * Reproduz um caso real: um dos tipos de referência ("P/N BIAGIO", por
     * exemplo) pode repetir o próprio partNumber em vez de trazer um código
     * de conversão de verdade.
     */
    public function test_excludes_a_referencia_that_just_restates_the_partnumber(): void
    {
        $product = BiagioTurbosProductParser::extractDetail($this->detailJson([
            'tiposReferencias' => [
                ['tipo' => 'P/N BIAGIO', 'referencias' => [
                    ['referencia' => '01030001'],
                    ['referencia' => '5821703101'],
                ]],
            ],
        ]), 'https://x');

        $this->assertSame(['5821703101'], $product['conversoes']);
    }

    public function test_returns_null_imagem_when_there_is_no_foto_entry(): void
    {
        $product = BiagioTurbosProductParser::extractDetail($this->detailJson([
            'turbo' => array_merge($this->baseTurbo(), [
                'arquivosLeves' => [['id' => 844, 'tipo' => 'medidasMontagem', 'url' => '/turbos/arquivos/844']],
            ]),
        ]), 'https://x');

        $this->assertNull($product['imagem_url']);
    }

    public function test_falls_back_descricao_to_codigo_when_modelo_is_absent(): void
    {
        $product = BiagioTurbosProductParser::extractDetail($this->detailJson([
            'turbo' => array_merge($this->baseTurbo(), ['modelo' => null]),
        ]), 'https://x');

        $this->assertSame('01030001', $product['descricao']);
    }

    private function baseTurbo(): array
    {
        return [
            'partNumber' => '01030001',
            'modelo' => 'BBV145AT',
            'linha' => ['nome' => 'BBV'],
            'arquivosLeves' => [],
            'ligacoes' => [],
        ];
    }
}
