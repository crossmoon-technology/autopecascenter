<?php

namespace Tests\Feature\Services\Basso3b;

use App\Services\Basso3b\Basso3bProductParser;
use Tests\TestCase;

class Basso3bProductParserTest extends TestCase
{
    private function listingRow(string $id, string $codigo): string
    {
        return <<<HTML
            <tr>
                <td><a href="/Articulo/Details/{$id}">{$codigo}</a></td>
                <td></td><td>45</td><td>31.45</td><td>89.10</td>
            </tr>
            HTML;
    }

    private function detailPage(array $overrides = []): string
    {
        $attrs = array_merge([
            'codigo' => '1001-AC',
            'tipoProd' => 'V&#225;lvulas',
            'aplicacoes' => '<tr><td><a href="/Aplicacion/Details/1"><span></span></a></td><td>L1, 150 C.C., 2 Valve</td><td>HONDA</td><td>CG TITAN</td><td>2004 - 2013</td></tr>',
            'intercambios' => '<tr><td>ORIGINAL</td><td>14711KW1900</td></tr><tr><td>KS ( BRASIL )</td><td>50017785K</td></tr>',
            'imagem' => '<img id="zoom_01" src="/content/imagenes/small/1001-AC.jpg" alt="1001-AC" data-zoom-image="/content/imagenes/large/1001-AC.jpg" class="img-responsive" />',
        ], $overrides);

        return <<<HTML
            <dl class="dl-horizontal">
                <dt>Tipo Prod</dt>
                <dd>{$attrs['tipoProd']}</dd>
                <dt>Producto</dt>
                <dd>{$attrs['codigo']}</dd>
            </dl>
            <div id="aplicacionesModelos">
                <table class="table table-striped">
                    <tr><th></th><th>Motor</th><th>Marca</th><th>Modelo</th><th>A&#241;os</th></tr>
                    {$attrs['aplicacoes']}
                </table>
            </div>
            <div id="fabricantes">
                <table class="table table-striped">
                    <tr><th>Fabricante</th><th>Intercambio</th></tr>
                    {$attrs['intercambios']}
                </table>
            </div>
            <div id="Imagen">
                {$attrs['imagem']}
            </div>
            HTML;
    }

    public function test_extracts_id_and_codigo_from_listing_rows(): void
    {
        $body = $this->listingRow('50579', '1001-AC').$this->listingRow('50580', '1001-B');

        $products = Basso3bProductParser::extractListing($body);

        $this->assertSame([
            ['id' => 50579, 'codigo' => '1001-AC'],
            ['id' => 50580, 'codigo' => '1001-B'],
        ], $products);
    }

    public function test_returns_empty_array_when_there_are_no_listing_rows(): void
    {
        $this->assertSame([], Basso3bProductParser::extractListing('<html></html>'));
    }

    public function test_extracts_codigo_grupo_aplicacao_conversoes_and_imagem_from_a_detail_page(): void
    {
        $product = Basso3bProductParser::extractDetail($this->detailPage());

        $this->assertSame('1001-AC', $product['codigo']);
        $this->assertSame('1001-AC', $product['descricao']);
        $this->assertSame('Válvulas', $product['grupo']);
        $this->assertSame('HONDA CG TITAN (L1, 150 C.C., 2 Valve, 2004 - 2013)', $product['aplicacao']);
        $this->assertSame(['14711KW1900', '50017785K'], $product['conversoes']);
        $this->assertSame('/content/imagenes/small/1001-AC.jpg', $product['imagem_url']);
    }

    public function test_returns_null_when_the_producto_field_is_missing(): void
    {
        $this->assertNull(Basso3bProductParser::extractDetail('<html>sem dados</html>'));
    }

    /**
     * Reproduz um caso real: "Años" vazio (só traços/espaço) não deve virar
     * um detalhe fantasma tipo "HONDA NX 150 ( - )".
     */
    public function test_omits_a_blank_anos_value_from_the_aplicacao_summary(): void
    {
        $aplicacoes = '<tr><td><a href="#"><span></span></a></td><td></td><td>HONDA</td><td>NX 150</td><td> - </td></tr>';

        $product = Basso3bProductParser::extractDetail($this->detailPage(['aplicacoes' => $aplicacoes]));

        $this->assertSame('HONDA NX 150', $product['aplicacao']);
    }

    public function test_joins_multiple_aplicacao_rows_with_a_comma(): void
    {
        $aplicacoes = '<tr><td><a href="#"><span></span></a></td><td>Motor A</td><td>HONDA</td><td>CG TITAN</td><td>2004 - 2013</td></tr>'
            .'<tr><td><a href="#"><span></span></a></td><td>Motor B</td><td>YAMAHA</td><td>YBR</td><td>2010</td></tr>';

        $product = Basso3bProductParser::extractDetail($this->detailPage(['aplicacoes' => $aplicacoes]));

        $this->assertSame('HONDA CG TITAN (Motor A, 2004 - 2013), YAMAHA YBR (Motor B, 2010)', $product['aplicacao']);
    }

    public function test_returns_null_aplicacao_when_there_are_no_application_rows(): void
    {
        $product = Basso3bProductParser::extractDetail($this->detailPage(['aplicacoes' => '']));

        $this->assertNull($product['aplicacao']);
    }

    public function test_returns_null_conversoes_when_there_are_no_intercambio_rows(): void
    {
        $product = Basso3bProductParser::extractDetail($this->detailPage(['intercambios' => '']));

        $this->assertNull($product['conversoes']);
    }

    public function test_excludes_an_intercambio_row_that_just_restates_the_primary_codigo(): void
    {
        $intercambios = '<tr><td>ORIGINAL</td><td>1001-AC</td></tr><tr><td>KS ( BRASIL )</td><td>50017785K</td></tr>';

        $product = Basso3bProductParser::extractDetail($this->detailPage(['intercambios' => $intercambios]));

        $this->assertSame(['50017785K'], $product['conversoes']);
    }

    /**
     * Reproduz o placeholder real do site quando não há foto — não deve virar
     * uma imagem "de mentira".
     */
    public function test_returns_null_imagem_when_the_site_shows_the_not_available_placeholder(): void
    {
        $product = Basso3bProductParser::extractDetail($this->detailPage([
            'imagem' => '<img src="/content/imagenes/sitio/nodisponible.jpg" alt="Img No Disponible" class="img-responsive" />',
        ]));

        $this->assertNull($product['imagem_url']);
    }
}
