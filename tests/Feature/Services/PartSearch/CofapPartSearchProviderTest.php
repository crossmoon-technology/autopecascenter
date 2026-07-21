<?php

namespace Tests\Feature\Services\PartSearch;

use App\Services\PartSearch\Providers\CofapPartSearchProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CofapPartSearchProviderTest extends TestCase
{
    public function test_parses_results_from_the_search_results_markup(): void
    {
        Http::fake([
            'mmcofap.com.br/*' => Http::response(<<<'HTML'
                <html><body>
                <section class="resultados-busca">
                    <div class="specs specs-busca linha-1">
                        <div class="imagem">
                            <img class="trigger-lightbox" src="https://mmcofap.com.br/wp-content/uploads/catalogo//16002.jpg">
                        </div>
                        <div class = 'item-title'>
                            <a href="https://mmcofap.com.br/busca-catalogo/?busca=16002" target="_blank">AMORTECEDOR 16002 PORTA TRASEIRA</a><i>VOLKSWAGEN - </i>VARIANT II</a>
                        </div>
                    </div>
                    <div class="specs specs-busca linha-1">
                        <div class="imagem">
                            <img class="trigger-lightbox" src="https://mmcofap.com.br/wp-content/uploads/catalogo//16006.jpg">
                        </div>
                        <div class = 'item-title'>
                            <a href="https://mmcofap.com.br/busca-catalogo/?busca=16006" target="_blank">AMORTECEDOR 16006 PORTA TRASEIRA</a><i>CHEVROLET - </i>CHEVETTE</a>
                        </div>
                    </div>
                </section>
                </body></html>
                HTML, 200),
        ]);

        $results = (new CofapPartSearchProvider)->search('amortecedor');

        $this->assertCount(2, $results);

        $first = $results->first();
        $this->assertSame('16002', $first->codigo);
        $this->assertSame('AMORTECEDOR 16002 PORTA TRASEIRA', $first->descricao);
        $this->assertSame('VOLKSWAGEN', $first->montadora);
        $this->assertSame('VARIANT II', $first->modelo);
        $this->assertSame('https://mmcofap.com.br/wp-content/uploads/catalogo//16002.jpg', $first->imagem_url);
    }

    public function test_throws_when_the_request_fails(): void
    {
        Http::fake([
            'mmcofap.com.br/*' => Http::response('', 500),
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        (new CofapPartSearchProvider)->search('amortecedor');
    }

    public function test_returns_an_empty_collection_when_there_are_no_results(): void
    {
        Http::fake([
            'mmcofap.com.br/*' => Http::response('<html><body><section class="resultados-busca"></section></body></html>', 200),
        ]);

        $results = (new CofapPartSearchProvider)->search('xyz-nao-existe');

        $this->assertTrue($results->isEmpty());
    }

    public function test_parses_the_single_product_layout_returned_for_an_exact_code_match(): void
    {
        Http::fake([
            'mmcofap.com.br/*' => Http::response(<<<'HTML'
                <html><body>
                <div class="result-veiculo cofap"><h1>16002<br/>MOLAS A GÁS</h1></div>
                <div class="row busca-codigo-result">
                    <div class="col-md-12">
                        <div class="specs">
                            <div class="imagem">
                                <img class="trigger-lightbox" src="https://mmcofap.com.br/wp-content/uploads/catalogo//16002.jpg">
                            </div>
                            <div class="content">
                                <div class="item"><div class="item-title">Marca</div><div class="item-value">COFAP</div></div>
                                <div class="item"><div class="item-title">Código</div><div class="item-value">16002</div></div>
                                <div class="item"><div class="item-title">Descrição do Grupo</div><div class="item-value">MOLA A GÁS</div></div>
                            </div>
                        </div>
                        <h1 class="aplicacoes">Compre Agora</h1>
                        <div class="specs two-column compre-agora" style="gap: 15px;">
                            <a href="https://lista.mercadolivre.com.br/x" target="_blank">Mercado Livre Cofap</a>
                        </div>
                        <h1 class="aplicacoes">Aplicações:</h1>
                        <div class="specs two-column">
                            <div class="content">
                                <div class="item"><div class="item-title">Montadora</div><div class="item-value">VOLKSWAGEN</div></div>
                                <div class="item"><div class="item-title">Veículo</div><div class="item-value">VARIANT II</div></div>
                                <div class="item"><div class="item-title">Modelo</div><div class="item-value">2ª GERAÇÃO</div></div>
                                <div class="item"><div class="item-title">Ano</div><div class="item-value">1978 ... 1981</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                </body></html>
                HTML, 200),
        ]);

        $results = (new CofapPartSearchProvider)->search('16002');

        $this->assertCount(1, $results);

        $first = $results->first();
        $this->assertSame('16002', $first->codigo);
        $this->assertSame('MOLA A GÁS', $first->descricao);
        $this->assertSame('VOLKSWAGEN', $first->montadora);
        $this->assertSame('VARIANT II 2ª GERAÇÃO (1978 ... 1981)', $first->modelo);
        $this->assertSame('https://mmcofap.com.br/wp-content/uploads/catalogo//16002.jpg', $first->imagem_url);
    }
}
