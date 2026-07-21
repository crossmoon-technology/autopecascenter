<?php

namespace Tests\Feature\Services\PartSearch;

use App\Services\PartSearch\Providers\HipperFreiosPartSearchProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HipperFreiosPartSearchProviderTest extends TestCase
{
    public function test_parses_results_from_the_search_results_table(): void
    {
        Http::fake([
            'hipperfreios.com.br/*' => Http::response(<<<'HTML'
                <html><body>
                <table class="lista-tabela tabela-busca table clickable-itens table-responsive">
                    <thead>
                        <tr><th>Imagem</th><th>Montadora</th><th>Veículo</th><th>Detalhe</th><th>Ano</th><th>Produto</th><th>Eixo</th><th>Código</th></tr>
                    </thead>
                    <tr valign="middle" class="hover-table transition" data-href="/pt-br/produto/cubo-roda-chevrolet-a10-pick-up-dianteiro-hfcd-29a-09298604/p:143/m:845/e:D">
                        <td><img src="./slir/w106-h80/img/fotoSemfoto.jpg" class="block" alt="HFCD 29A"></td>
                        <td>CHEVROLET </td>
                        <td>A10</td>
                        <td>PICK-UP</td>
                        <td>1969 até 1984</td>
                        <td class="upper">Cubo de Roda</td>
                        <td>D</td>
                        <td>HFCD 29A</td>
                    </tr>
                </table>
                </body></html>
                HTML, 200),
        ]);

        $results = (new HipperFreiosPartSearchProvider)->search('HFCD 29A');

        $this->assertCount(1, $results);

        $first = $results->first();
        $this->assertSame('HFCD 29A', $first->codigo);
        $this->assertSame('Cubo de Roda', $first->descricao);
        $this->assertSame('CHEVROLET', $first->montadora);
        $this->assertSame('A10 PICK-UP (1969 até 1984)', $first->modelo);
        $this->assertSame('https://www.hipperfreios.com.br/slir/w106-h80/img/fotoSemfoto.jpg', $first->imagem_url);
        $this->assertSame(
            'https://www.hipperfreios.com.br/pt-br/produto/cubo-roda-chevrolet-a10-pick-up-dianteiro-hfcd-29a-09298604/p:143/m:845/e:D',
            $first->product_url
        );
    }

    public function test_throws_when_the_request_fails(): void
    {
        Http::fake([
            'hipperfreios.com.br/*' => Http::response('', 500),
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        (new HipperFreiosPartSearchProvider)->search('HF 21');
    }
}
