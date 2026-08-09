<?php

namespace Tests\Feature\Services\MgFreios;

use App\Services\MgFreios\MgFreiosProductParser;
use Tests\TestCase;

class MgFreiosProductParserTest extends TestCase
{
    private function listingBody(array $products, int $total): string
    {
        $json = json_encode([
            'siteSearchResults' => $total,
            'listProducts' => $products,
        ]);

        return <<<HTML
            <html><body>
            <script>dataLayer = [{$json}];</script>
            </body></html>
            HTML;
    }

    private function detailBody(array $breadcrumbDetails, string $description): string
    {
        $json = json_encode(['breadcrumbDetails' => $breadcrumbDetails]);

        return <<<HTML
            <html><body>
            <div id="descricao" class="prodBox"><div class="board"><div class="board_htm description">
                {$description}
            </div></div></div>
            <script>dataLayer = [{$json}]</script>
            </body></html>
            HTML;
    }

    public function test_extracts_listings_and_total_from_the_listing_data_layer(): void
    {
        $body = $this->listingBody([
            ['reference' => 'MG-8002', 'nameProduct' => 'Jogo de lona de freio', 'brand' => 'Hyundai', 'urlImage' => 'https://x/img.jpg', 'urlProduct' => 'https://x/produto'],
        ], total: 893);

        $result = MgFreiosProductParser::extractListing($body);

        $this->assertSame(893, $result['total']);
        $this->assertCount(1, $result['listings']);
        $this->assertSame([
            'codigo' => 'MG-8002',
            'descricao' => 'Jogo de lona de freio',
            'fabricante' => 'Hyundai',
            'imagem_url' => 'https://x/img.jpg',
            'url_produto' => 'https://x/produto',
        ], $result['listings'][0]);
    }

    public function test_ignores_a_listing_entry_without_a_reference(): void
    {
        $body = $this->listingBody([
            ['nameProduct' => 'Sem código', 'brand' => 'X'],
        ], total: 1);

        $result = MgFreiosProductParser::extractListing($body);

        $this->assertSame([], $result['listings']);
    }

    public function test_returns_empty_listings_and_null_total_when_there_is_no_data_layer(): void
    {
        $result = MgFreiosProductParser::extractListing('<html><body>sem dados</body></html>');

        $this->assertSame([], $result['listings']);
        $this->assertNull($result['total']);
    }

    public function test_extracts_grupo_and_subgrupo_from_breadcrumb_details_sorted_by_level(): void
    {
        $body = $this->detailBody([
            ['id' => 1467, 'name' => 'Lonas', 'level' => 2],
            ['id' => 1491, 'name' => 'Lona de Freio', 'level' => 1],
        ], 'MG-8002, Hyundai, Jogo de lona de freio, County,');

        $result = MgFreiosProductParser::extractDetail($body);

        $this->assertSame('Lona de Freio', $result['grupo']);
        $this->assertSame('Lonas', $result['subgrupo']);
    }

    /**
     * Reproduz o formato real: o cross-reference OEM só existe como um
     * trecho "ORIG. <código>" no fim da descrição em texto livre — nem
     * sempre presente, e pode ter múltiplos códigos separados por " - ".
     */
    public function test_extracts_multiple_oem_codes_from_a_trailing_orig_segment(): void
    {
        $body = $this->detailBody(
            [['id' => 1, 'name' => 'Pastilha de freio', 'level' => 1]],
            'MG-1022, Iveco, Jogo de pastilhas de freio (D), Daily 35 S14 (todas), ORIG. 42555881 - 42561355'
        );

        $result = MgFreiosProductParser::extractDetail($body);

        $this->assertSame(['42555881', '42561355'], $result['conversoes']);
    }

    public function test_a_single_oem_code_with_an_internal_dash_is_not_split(): void
    {
        $body = $this->detailBody(
            [['id' => 1, 'name' => 'Cilindros de roda', 'level' => 1]],
            'MG-7053, Ford, Cilindro de roda (T) (LE), F4000, ORIG. BG5T.2262-AA'
        );

        $result = MgFreiosProductParser::extractDetail($body);

        $this->assertSame(['BG5T.2262-AA'], $result['conversoes']);
    }

    public function test_returns_null_conversoes_when_the_description_has_no_orig_segment(): void
    {
        $body = $this->detailBody(
            [['id' => 1, 'name' => 'Sapatas de freio', 'level' => 1]],
            'MG-2256, Toyota, Acionador automático do freio (T) (LE), Hilux,'
        );

        $result = MgFreiosProductParser::extractDetail($body);

        $this->assertNull($result['conversoes']);
    }

    public function test_returns_null_grupo_and_subgrupo_when_there_is_no_breadcrumb(): void
    {
        $result = MgFreiosProductParser::extractDetail('<html><body>sem dados</body></html>');

        $this->assertNull($result['grupo']);
        $this->assertNull($result['subgrupo']);
        $this->assertNull($result['conversoes']);
    }
}
