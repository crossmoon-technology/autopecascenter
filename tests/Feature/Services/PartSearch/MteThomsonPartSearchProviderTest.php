<?php

namespace Tests\Feature\Services\PartSearch;

use App\Services\PartSearch\Providers\MteThomsonPartSearchProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MteThomsonPartSearchProviderTest extends TestCase
{
    private function resultPage(): string
    {
        return <<<'HTML'
            <html><body><table><tbody>
            <tr class="grid-row  custom-border-cinza">
                <td class="grid-cell" data-name="" style="display:none;"></td>
                <td class="grid-cell" data-name="" style="display:none;"></td>
                <td class="grid-cell" data-name="">
                    <a href="/pt/br/produto/detalhes/206.82/valvula-termostatica"><img src="https://cdn.mte-thomson.com.br/x/206.82.jpg" /></a>
                </td>
                <td class="grid-cell" data-name="PARTNUMBER">
                    <a href="/pt/br/produto/detalhes/206.82/valvula-termostatica"><strong>206.82</strong></a>
                </td>
                <td class="grid-cell" data-name="NOME_LINHA_PRODUTO">
                    <a href="/pt/br/produto/detalhes/206.82/valvula-termostatica">VÁLVULA TERMOSTÁTICA</a>
                </td>
                <td class="grid-cell" data-name="">
                    <ul class="list-unstyled"></ul>
                </td>
                <td class="grid-cell" data-name="">
                    <label class="custom-color-cinza"><strong></strong></label>
                </td>
                <td class="grid-cell" data-name="">
                    <ul class="list-unstyled">
                        <li class="pb-1">133747</li>
                        <li class="pb-1">91500723</li>
                    </ul>
                </td>
            </tr>
            </tbody></table></body></html>
            HTML;
    }

    /**
     * Página com o widget de paginação do site (só aparece quando tem mais de uma
     * página de verdade — ver MteThomsonSearchResultParser).
     */
    private function paginatedResultPage(int $total, int $lastPage): string
    {
        $rows = $this->resultPage();
        $pagerLinks = collect(range(1, $lastPage))
            ->map(fn (int $page) => "<li class=\"page-item\"><a class=\"page-link\" href=\"?grid-page={$page}\">{$page}</a></li>")
            ->implode('');

        return str_replace(
            '</tbody></table></body></html>',
            '</tbody></table>'.
            '<div class="grid-footer">'.
            "<label class=\"custom-color-cinza\">Total de itens:</label> <label class=\"custom-color-cinza\">{$total}</label>".
            "<ul class=\"pagination\">{$pagerLinks}</ul>".
            '</div></body></html>',
            $rows
        );
    }

    public function test_searches_by_own_code_with_partial_matching_and_maps_results(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/pt/br/produto/pesquisar/206.82-1/false/lp-todas*' => Http::response($this->resultPage(), 200),
        ]);

        $page = (new MteThomsonPartSearchProvider)->search('206.82');

        $this->assertCount(1, $page->results);
        $this->assertSame('206.82', $page->results->first()->codigo);
        $this->assertSame('VÁLVULA TERMOSTÁTICA', $page->results->first()->descricao);
        $this->assertSame('https://cdn.mte-thomson.com.br/x/206.82.jpg', $page->results->first()->imagem_url);
        $this->assertSame(['133747', '91500723'], $page->results->first()->conversoes);
        $this->assertSame('https://cate.mte-thomson.com.br/pt/br/produto/detalhes/206.82/valvula-termostatica', $page->results->first()->product_url);
        $this->assertSame(1, $page->currentPage);
        $this->assertSame(1, $page->lastPage);
        $this->assertNull($page->total);
        $this->assertFalse($page->hasMultiplePages());
    }

    public function test_url_encodes_the_query_in_the_path(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response($this->resultPage(), 200),
        ]);

        (new MteThomsonPartSearchProvider)->search('a/b c');

        Http::assertSent(fn ($request) => str_contains($request->url(), rawurlencode('a/b c').'-1/false/lp-todas'));
    }

    public function test_returns_an_empty_collection_when_there_are_no_results(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response('<html><body>sem resultado</body></html>', 200),
        ]);

        $page = (new MteThomsonPartSearchProvider)->search('nao-existe');

        $this->assertCount(0, $page->results);
    }

    public function test_throws_when_the_request_fails(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response('', 500),
        ]);

        $this->expectException(RequestException::class);

        (new MteThomsonPartSearchProvider)->search('206.82');
    }

    /**
     * Reproduz uma falha real em produção: um timeout de conexão (cURL error
     * 28, 0 bytes recebidos) numa busca de verdade — sem nenhum retry
     * configurado, um único blip transitório derrubava a busca inteira.
     */
    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->resultPage(), 200),
        ]);

        $page = (new MteThomsonPartSearchProvider)->search('206.82');

        $this->assertCount(1, $page->results);
    }

    public function test_sends_the_requested_page_as_the_grid_page_query_param(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response($this->paginatedResultPage(total: 101, lastPage: 9), 200),
        ]);

        (new MteThomsonPartSearchProvider)->search('100', 3);

        Http::assertSent(fn ($request) => (string) $request['grid-page'] === '3');
    }

    public function test_parses_current_page_last_page_and_total_from_a_multi_page_result(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response($this->paginatedResultPage(total: 101, lastPage: 9), 200),
        ]);

        $page = (new MteThomsonPartSearchProvider)->search('100', 3);

        $this->assertSame(3, $page->currentPage);
        $this->assertSame(9, $page->lastPage);
        $this->assertSame(101, $page->total);
        $this->assertTrue($page->hasMultiplePages());
    }

    public function test_single_result_search_has_no_pagination(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response($this->resultPage(), 200),
        ]);

        $page = (new MteThomsonPartSearchProvider)->search('206.82');

        $this->assertSame(1, $page->lastPage);
        $this->assertNull($page->total);
        $this->assertFalse($page->hasMultiplePages());
    }
}
