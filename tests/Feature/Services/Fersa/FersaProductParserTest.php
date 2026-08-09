<?php

namespace Tests\Feature\Services\Fersa;

use App\Services\Fersa\FersaProductParser;
use Tests\TestCase;

class FersaProductParserTest extends TestCase
{
    private function hit(array $overrides = []): array
    {
        return array_merge([
            'name' => '6204-2RS2-C3',
            'sku' => ['6204-2RS2-C3-NKE', '6204 2RS2 C3 NKE', '62042RS2C3NKE'],
            'manufacturer' => 'NKE',
            'categories' => [
                'level0' => ['Rolamentos de Esferas'],
                'level1' => ['Rolamentos de Esferas /// Rolamentos Radiais de Esferas'],
            ],
            'image_url' => 'https://brasil.fersa.com/media/6204.png',
            'fersa_cross_references' => null,
            'fersa_cross_references_additional' => null,
        ], $overrides);
    }

    public function test_extracts_the_basic_fields_using_the_first_sku_as_codigo(): void
    {
        $product = FersaProductParser::extractProduct($this->hit());

        $this->assertSame('6204-2RS2-C3-NKE', $product['codigo']);
        $this->assertSame('6204-2RS2-C3', $product['descricao']);
        $this->assertSame('NKE', $product['fabricante']);
        $this->assertSame('Rolamentos de Esferas', $product['grupo']);
        $this->assertSame('Rolamentos de Esferas /// Rolamentos Radiais de Esferas', $product['subgrupo']);
        $this->assertSame('https://brasil.fersa.com/media/6204.png', $product['imagem_url']);
    }

    public function test_returns_null_when_there_is_no_sku_at_all(): void
    {
        $this->assertNull(FersaProductParser::extractProduct($this->hit(['sku' => []])));
        $this->assertNull(FersaProductParser::extractProduct($this->hit(['sku' => null])));
    }

    /**
     * Reproduz um produto real (6202-2RS C3): a lista mistura strings soltas
     * "CÓDIGO~MARCA" com arrays aninhados de variantes de formatação do
     * MESMO código+marca — só a primeira variante de cada grupo é mantida.
     */
    public function test_extracts_codes_from_both_plain_and_nested_variant_entries(): void
    {
        $product = FersaProductParser::extractProduct($this->hit([
            'fersa_cross_references' => [
                'CH12549~RENAULT',
                [
                    '38751-590-004~DELCO',
                    '38751 590 004~DELCO',
                    '38751590004~DELCO',
                ],
            ],
        ]));

        $this->assertSame(['CH12549', '38751-590-004'], $product['conversoes']);
    }

    public function test_ignores_a_bare_brand_name_with_no_digits(): void
    {
        $product = FersaProductParser::extractProduct($this->hit([
            'fersa_cross_references' => ['JOHN DEERE', 'OTHER'],
        ]));

        $this->assertNull($product['conversoes']);
    }

    public function test_keeps_a_bare_code_with_no_brand_marker(): void
    {
        $product = FersaProductParser::extractProduct($this->hit([
            'fersa_cross_references' => ['MF80111303'],
        ]));

        $this->assertSame(['MF80111303'], $product['conversoes']);
    }

    /**
     * Reproduz um produto real que quebrou a importação: uma entrada não é
     * "CÓDIGO~MARCA" nem um array de variantes — é uma única string com 16
     * códigos DIFERENTES separados por vírgula. Sem separar cada um, a
     * entrada inteira virava um "código" de mais de 255 caracteres e
     * estourava a coluna do banco.
     */
    public function test_splits_a_comma_joined_list_of_distinct_codes_within_one_entry(): void
    {
        $product = FersaProductParser::extractProduct($this->hit([
            'fersa_cross_references' => [
                'A3854100231,A3124101231,A3854100031,4101431,3124101231',
            ],
        ]));

        $this->assertSame(['A3854100231', 'A3124101231', 'A3854100031', '4101431', '3124101231'], $product['conversoes']);
    }

    public function test_merges_both_cross_reference_fields(): void
    {
        $product = FersaProductParser::extractProduct($this->hit([
            'fersa_cross_references' => ['CH12549~RENAULT'],
            'fersa_cross_references_additional' => ['10475023~LUCAS'],
        ]));

        $this->assertSame(['CH12549', '10475023'], $product['conversoes']);
    }

    public function test_returns_null_grupo_and_subgrupo_when_categories_are_absent(): void
    {
        $product = FersaProductParser::extractProduct($this->hit(['categories' => []]));

        $this->assertNull($product['grupo']);
        $this->assertNull($product['subgrupo']);
    }
}
