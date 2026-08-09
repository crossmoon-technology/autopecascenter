<?php

namespace Tests\Feature\Services\MteThomson;

use App\Services\MteThomson\MteThomsonSearchResultParser;
use Tests\TestCase;

class MteThomsonSearchResultParserTest extends TestCase
{
    private function row(string $codigo, string $descricao, string $imagemSrc, array $aplicacoes = [], array $conversoes = [], ?int $moreAplicacoes = null, ?int $moreConversoes = null): string
    {
        $productPath = "/pt/br/produto/detalhes/{$codigo}/slug";

        $aplicacaoLis = collect($aplicacoes)->map(fn (string $a) => "<li class=\"pb-1\">{$a}</li>")->implode('');
        if ($moreAplicacoes !== null) {
            $aplicacaoLis .= "<li class=\"pb-1\"><a href=\"{$productPath}\">+ ({$moreAplicacoes}) aplicações</a></li>";
        }

        $conversaoLis = collect($conversoes)->map(fn (string $c) => "<li class=\"pb-1\">{$c}</li>")->implode('');
        if ($moreConversoes !== null) {
            $conversaoLis .= "<li class=\"pb-1\"><a href=\"{$productPath}\">+ ({$moreConversoes}) OEMs</a></li>";
        }

        return <<<HTML
            <tr class="grid-row  custom-border-cinza">
                <td class="grid-cell" data-name="" style="display:none;"></td>
                <td class="grid-cell" data-name="" style="display:none;"></td>
                <td class="grid-cell" data-name="">
                    <a href="{$productPath}"><img src="{$imagemSrc}" /></a>
                </td>
                <td class="grid-cell" data-name="PARTNUMBER">
                    <a href="{$productPath}"><strong>{$codigo}</strong></a>
                </td>
                <td class="grid-cell" data-name="NOME_LINHA_PRODUTO">
                    <a href="{$productPath}">{$descricao}</a>
                </td>
                <td class="grid-cell" data-name="">
                    <ul class="list-unstyled">{$aplicacaoLis}</ul>
                </td>
                <td class="grid-cell" data-name="">
                    <label class="custom-color-cinza"><strong></strong></label>
                </td>
                <td class="grid-cell" data-name="">
                    <ul class="list-unstyled">{$conversaoLis}</ul>
                </td>
            </tr>
            HTML;
    }

    private function page(string $rows): string
    {
        return "<html><body><table><tbody>{$rows}</tbody></table></body></html>";
    }

    public function test_extracts_codigo_descricao_imagem_and_product_path(): void
    {
        $html = $this->page($this->row('5072', 'PLUG ELETRÔNICO - AR', 'https://cdn.mte-thomson.com.br/x/5072.jpg'));

        $results = MteThomsonSearchResultParser::extractResults($html);

        $this->assertCount(1, $results);
        $this->assertSame('5072', $results[0]['codigo']);
        $this->assertSame('PLUG ELETRÔNICO - AR', $results[0]['descricao']);
        $this->assertSame('https://cdn.mte-thomson.com.br/x/5072.jpg', $results[0]['imagem_src']);
        $this->assertSame('/pt/br/produto/detalhes/5072/slug', $results[0]['product_path']);
    }

    public function test_extracts_aplicacao_and_conversoes_excluding_the_see_more_link(): void
    {
        $html = $this->page($this->row(
            '5072',
            'PLUG ELETRÔNICO - AR',
            'img.jpg',
            aplicacoes: ['MERCEDES-BENZ - A160 - 1.6', 'MERCEDES-BENZ - A190 - 1.9'],
            conversoes: ['05149209AA', '4862635AA'],
            moreAplicacoes: 53,
            moreConversoes: 48,
        ));

        $results = MteThomsonSearchResultParser::extractResults($html);

        $this->assertSame('MERCEDES-BENZ - A160 - 1.6, MERCEDES-BENZ - A190 - 1.9', $results[0]['aplicacao']);
        $this->assertSame(['05149209AA', '4862635AA'], $results[0]['conversoes']);
    }

    public function test_null_aplicacao_and_empty_conversoes_when_the_lists_are_empty(): void
    {
        $html = $this->page($this->row('206.82', 'VÁLVULA TERMOSTÁTICA', 'img.jpg'));

        $results = MteThomsonSearchResultParser::extractResults($html);

        $this->assertNull($results[0]['aplicacao']);
        $this->assertSame([], $results[0]['conversoes']);
    }

    public function test_extracts_multiple_rows_in_order(): void
    {
        $html = $this->page(
            $this->row('A1', 'Peça Um', 'a1.jpg').
            $this->row('A2', 'Peça Dois', 'a2.jpg')
        );

        $results = MteThomsonSearchResultParser::extractResults($html);

        $this->assertSame(['A1', 'A2'], array_column($results, 'codigo'));
    }

    public function test_returns_an_empty_array_when_there_are_no_result_rows(): void
    {
        $this->assertSame([], MteThomsonSearchResultParser::extractResults('<html><body>Nenhum resultado</body></html>'));
    }

    public function test_extracts_total_from_the_total_de_itens_label(): void
    {
        $html = '<div class="grid-footer"><label class="custom-color-cinza">Total de itens:</label> <label class="custom-color-cinza">101</label></div>';

        $this->assertSame(101, MteThomsonSearchResultParser::extractTotal($html));
    }

    public function test_extract_total_is_null_when_the_label_is_absent(): void
    {
        $this->assertNull(MteThomsonSearchResultParser::extractTotal('<div class="grid-footer"></div>'));
    }

    public function test_extracts_the_last_page_as_the_highest_grid_page_link(): void
    {
        $html = <<<'HTML'
            <ul class="pagination">
                <li class="page-item"><a class="page-link" href="?grid-page=1">1</a></li>
                <li class="page-item"><a class="page-link" href="?grid-page=2">2</a></li>
                <li class="page-item"><a class="page-link" href="?grid-page=9">9</a></li>
            </ul>
            HTML;

        $this->assertSame(9, MteThomsonSearchResultParser::extractLastPage($html));
    }

    public function test_extract_last_page_defaults_to_one_when_there_is_no_pagination_widget(): void
    {
        $this->assertSame(1, MteThomsonSearchResultParser::extractLastPage('<div class="grid-footer"></div>'));
    }
}
