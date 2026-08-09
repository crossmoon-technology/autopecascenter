<?php

namespace Tests\Feature\Services\Kaer;

use App\Services\Kaer\KaerTitleParser;
use Tests\TestCase;

class KaerTitleParserTest extends TestCase
{
    public function test_splits_codigo_and_descricao_from_the_standard_title_format(): void
    {
        $parsed = KaerTitleParser::parse('41210621R - 712 - KIT REPARO ALAVANCA CÂMBIO');

        $this->assertSame('41210621R', $parsed['codigo']);
        $this->assertSame('KIT REPARO ALAVANCA CÂMBIO', $parsed['descricao']);
    }

    public function test_falls_back_to_the_full_title_when_it_does_not_have_the_expected_format(): void
    {
        $parsed = KaerTitleParser::parse('TITULO SEM SEPARADOR');

        $this->assertSame('TITULO SEM SEPARADOR', $parsed['codigo']);
        $this->assertSame('TITULO SEM SEPARADOR', $parsed['descricao']);
    }
}
