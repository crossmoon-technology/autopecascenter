<?php

namespace Tests\Feature\Services\Ideia2001;

use App\Services\Ideia2001\Ideia2001ProductFields;
use Tests\TestCase;

class Ideia2001ProductFieldsTest extends TestCase
{
    public function test_summarizes_aplicacoes_with_engine_year_range_and_fuel(): void
    {
        $summary = Ideia2001ProductFields::summarizeAplicacoes([
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
        ]);

        $this->assertSame('MERCEDES-BENZ 1215 C (OM 904 LA, 1999-2005, DIESEL)', $summary);
    }

    public function test_collapses_a_single_year_application_without_a_range(): void
    {
        $summary = Ideia2001ProductFields::summarizeAplicacoes([
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
        ]);

        $this->assertSame('SCANIA R 440 (2010)', $summary);
    }

    public function test_returns_null_for_no_applications(): void
    {
        $this->assertNull(Ideia2001ProductFields::summarizeAplicacoes([]));
    }

    /**
     * "906 030 03 20" e "A 906 030 03 20" são códigos DIFERENTES (prefixo "A"
     * é parte do número Mercedes-Benz) — não podem ser confundidos como a
     * mesma coisa reformatada.
     */
    public function test_extracts_conversoes_preserving_distinct_prefixed_codes(): void
    {
        $conversoes = Ideia2001ProductFields::extractConversoes([
            ['DescricaoFabricante' => 'MERCEDES-BENZ', 'NumerosProduto' => [
                ['NumeroProduto' => '906 030 03 20'],
                ['NumeroProduto' => 'A 906 030 03 20'],
            ]],
        ], '20060390601');

        $this->assertSame(['9060300320', 'A9060300320'], $conversoes);
    }

    public function test_excludes_a_cross_reference_that_matches_the_primary_code(): void
    {
        $conversoes = Ideia2001ProductFields::extractConversoes([
            ['DescricaoFabricante' => 'X', 'NumerosProduto' => [['NumeroProduto' => 'x 1']]],
        ], 'X1');

        $this->assertNull($conversoes);
    }

    public function test_returns_null_for_no_cross_references(): void
    {
        $this->assertNull(Ideia2001ProductFields::extractConversoes([], 'X1'));
    }
}
