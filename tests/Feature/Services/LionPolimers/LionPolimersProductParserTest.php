<?php

namespace Tests\Feature\Services\LionPolimers;

use App\Services\LionPolimers\LionPolimersProductParser;
use Tests\TestCase;

class LionPolimersProductParserTest extends TestCase
{
    private function attribute(string $name, string $value): array
    {
        return ['name' => $name, 'terms' => [['name' => $value]]];
    }

    private function product(array $overrides = []): array
    {
        return array_merge([
            'name' => '8796',
            'images' => [['src' => 'https://lionpolimers.com/wp-content/uploads/2026/07/8796.png']],
            'attributes' => [
                $this->attribute('Aplicação', 'MANGUEIRA INFERIOR DO RADIADOR'),
                $this->attribute('Código Original', "93283526\n93365488"),
                $this->attribute('Código Lion', '8796'),
                $this->attribute('Montadora', 'GM'),
                $this->attribute('Veículo (Ano)', "S10 (12/20)\nS10 2.8 TURBO DIESEL (12/22)"),
            ],
        ], $overrides);
    }

    public function test_extracts_codigo_descricao_grupo_aplicacao_conversoes_and_imagem(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([$this->product()]));

        $this->assertCount(1, $products);
        $product = $products[0];
        $this->assertSame('8796', $product['codigo']);
        $this->assertSame('MANGUEIRA INFERIOR DO RADIADOR', $product['descricao']);
        $this->assertSame('GM', $product['grupo']);
        $this->assertSame('GM S10 (12/20), GM S10 2.8 TURBO DIESEL (12/22)', $product['aplicacao']);
        $this->assertSame(['93283526', '93365488'], $product['conversoes']);
        $this->assertSame('https://lionpolimers.com/wp-content/uploads/2026/07/8796.png', $product['imagem_url']);
    }

    public function test_returns_empty_array_for_invalid_json(): void
    {
        $this->assertSame([], LionPolimersProductParser::extractListing('not json'));
    }

    public function test_ignores_a_product_without_a_name(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([
            array_merge($this->product(), ['name' => '']),
        ]));

        $this->assertSame([], $products);
    }

    public function test_falls_back_descricao_to_codigo_when_aplicacao_attribute_is_absent(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([
            array_merge($this->product(), ['attributes' => [
                $this->attribute('Montadora', 'GM'),
            ]]),
        ]));

        $this->assertSame('8796', $products[0]['descricao']);
    }

    public function test_returns_null_conversoes_when_codigo_original_is_absent(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([
            array_merge($this->product(), ['attributes' => [
                $this->attribute('Aplicação', 'MANGUEIRA'),
            ]]),
        ]));

        $this->assertNull($products[0]['conversoes']);
    }

    /**
     * Reproduz um caso real: um dos códigos originais é igual ao próprio
     * código Lion — não deve virar uma conversão fantasma.
     */
    public function test_excludes_a_codigo_original_that_just_restates_the_primary_codigo(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([
            array_merge($this->product(), ['attributes' => [
                $this->attribute('Código Original', "8796\n93365488"),
            ]]),
        ]));

        $this->assertSame(['93365488'], $products[0]['conversoes']);
    }

    public function test_returns_null_aplicacao_when_veiculo_attribute_is_absent(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([
            array_merge($this->product(), ['attributes' => [
                $this->attribute('Montadora', 'GM'),
            ]]),
        ]));

        $this->assertNull($products[0]['aplicacao']);
    }

    public function test_returns_null_imagem_when_there_are_no_images(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([
            array_merge($this->product(), ['images' => []]),
        ]));

        $this->assertNull($products[0]['imagem_url']);
    }

    public function test_extracts_multiple_products(): void
    {
        $products = LionPolimersProductParser::extractListing(json_encode([
            array_merge($this->product(), ['name' => '8796']),
            array_merge($this->product(), ['name' => '8797']),
        ]));

        $this->assertSame(['8796', '8797'], array_column($products, 'codigo'));
    }
}
