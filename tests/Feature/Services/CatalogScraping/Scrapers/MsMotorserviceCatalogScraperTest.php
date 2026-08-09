<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\MsMotorserviceCatalogScraper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MsMotorserviceCatalogScraperTest extends TestCase
{
    /**
     * Um padrão de Http::fake() que não bate com a URL real cai pra rede de
     * verdade por padrão (já aconteceu num teste da RPD nesta mesma sessão,
     * por causa de uma barra faltando no padrão) — preventStrayRequests() faz
     * qualquer chamada não coberta pelos fakes estourar aqui em vez de vazar
     * pro site real. Storage::fake('local') isola os checkpoints (ver
     * MsMotorserviceCatalogScraper) do disco de verdade — sem isso, um teste
     * anterior deixaria um checkpoint pra trás (mesma baseUrl = mesma chave)
     * e o próximo teste "resumiria" de um estado que não é dele.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('local');
    }

    private function listingPage(array $items, int $total): string
    {
        $json = json_encode(['data' => $items]);

        return <<<HTML
            <html><body>
            <strong id="cw-total-resultado-plural">{$total}</strong>
            <script id="__CW_DATA_LISTA_RESULTADO__" type="application/json">{$json}</script>
            </body></html>
            HTML;
    }

    private function detailPage(string $codigo, ?array $conversoes = null): string
    {
        $referenciasCruzada = $conversoes !== null
            ? [['DescricaoFabricante' => 'X', 'NumerosProduto' => collect($conversoes)->map(fn ($c) => ['NumeroProduto' => $c])->all()]]
            : [];

        $json = json_encode(['data' => [[
            'NumeroProduto' => $codigo,
            'DescricaoProduto' => "PEÇA {$codigo}",
            'DescricaoFabricante' => 'Kolbenschmidt (KS)',
            'DescricaoGrupoProduto' => 'GRUPO',
            'DescricaoSubGrupoProduto' => 'SUBGRUPO',
            'ArquivoFotoProduto' => "{$codigo}.jpg",
            'FabricantesAplicacao' => [],
            'ReferenciasCruzada' => $referenciasCruzada,
        ]]]);

        return <<<HTML
            <html><body>
            <script id="__CW_DATA_DETALHES_PRODUTO__" type="application/json">{$json}</script>
            </body></html>
            HTML;
    }

    public function test_scrapes_listing_and_detail_pages_end_to_end(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response($this->detailPage('X1', ['906 030 03 20']), 200),
        ]);

        $result = (new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
        $this->assertSame('X1', $result->products[0]['codigo']);
        $this->assertSame(['9060300320'], $result->products[0]['conversoes']);
        $this->assertSame('Kolbenschmidt (KS)', $result->products[0]['fabricante']);
        $this->assertSame('GRUPO', $result->products[0]['grupo']);
        $this->assertSame('SUBGRUPO', $result->products[0]['subgrupo']);
        $this->assertSame('https://www.c123.com.br/CatalogoExpresso/479/FotoProdWeb/dcp/X1.jpg', $result->products[0]['imagem_url']);
        $this->assertNotNull($result->source_version);
    }

    public function test_paginates_listing_until_the_total_is_reached(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 2), 200),
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([
                ['CodigoProduto' => 2, 'NumeroProduto' => 'X2'],
            ], total: 2), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br'))->scrape(null);

        $this->assertCount(2, $result->products);
    }

    /**
     * Mesma proteção da C123/RPD: se a listagem parar bem antes de bater o
     * total anunciado (diferença grande, não uma imprecisão pequena do
     * contador do site), é falha dura — nunca sobrescreve o catálogo com
     * dado parcial. Como a checagem acontece ANTES da etapa cara de detalhe
     * por produto, nenhum detalhes.php chega a ser chamado.
     */
    public function test_throws_before_fetching_any_detail_when_the_listing_falls_far_short_of_the_total(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 100), 200),
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([], total: 100), 200),
        ]);

        try {
            (new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br'))->scrape(null);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
            // esperado
        }

        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), 'detalhes.php'));
    }

    /**
     * Reproduz um caso real: uma raspagem ao vivo teve o marcador de total
     * (cw-total-resultado-plural) ausente/malformado numa página, sem gerar
     * nenhum erro HTTP — só produtos reais normalmente extraídos, até parar
     * numa página vazia bem mais cedo do que o catálogo de verdade (~101 de
     * ~4332 peças). Sem total conhecido, a antiga lógica calculava
     * "shortfall = 0" e aceitava isso como completo — nunca mais pode
     * silenciosamente aceitar dado incompleto só porque não deu pra
     * confirmar o total esperado.
     */
    public function test_throws_when_the_total_marker_never_resolves_even_after_reaching_an_empty_page(): void
    {
        $pageWithoutTotalMarker = <<<'HTML'
            <html><body>
            <script id="__CW_DATA_LISTA_RESULTADO__" type="application/json">{"data":[{"CodigoProduto":1,"NumeroProduto":"X1"}]}</script>
            </body></html>
            HTML;
        $emptyPageWithoutTotalMarker = <<<'HTML'
            <html><body>
            <script id="__CW_DATA_LISTA_RESULTADO__" type="application/json">{"data":[]}</script>
            </body></html>
            HTML;

        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($pageWithoutTotalMarker, 200),
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=2*' => Http::response($emptyPageWithoutTotalMarker, 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br'))->scrape(null);
    }

    /**
     * Reproduz um caso real: o contador "N produtos" da própria MS
     * Motorservice anuncia 4342, mas a paginação sempre termina numa página
     * vazia com só 4332 coletados — uma inconsistência real do próprio site,
     * confirmada ao vivo (refeita a checagem, não foi um blip). Uma pequena
     * diferença como essa, com uma página realmente vazia alcançada, não pode
     * travar o scraper pra sempre.
     */
    public function test_tolerates_a_small_shortfall_when_a_real_empty_page_was_reached(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 11), 200),
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=2*' => Http::response($this->listingPage([], total: 11), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    /**
     * Se a paginação nunca alcançou uma página vazia de verdade (parou por
     * ter batido o MAX_PAGES), a tolerância não se aplica — não há garantia
     * nenhuma de que aquilo foi um fim natural do catálogo.
     */
    public function test_does_not_tolerate_any_shortfall_when_no_empty_page_was_ever_reached(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 2), 200),
        ]);

        $scraper = new class('https://catweb.ms-motorservice.com.br') extends MsMotorserviceCatalogScraper
        {
            protected const int MAX_PAGES = 1;
        };

        $this->expectException(RuntimeException::class);

        $scraper->scrape(null);
    }

    /**
     * O fingerprint é calculado a partir só da listagem (barata) — se não
     * mudou, a etapa cara de detalhe por produto (a real custosa aqui, ~4300
     * requests num catálogo real) nem começa, igual à Kaer.
     */
    public function test_skips_the_expensive_detail_stage_when_the_fingerprint_is_unchanged(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $scraper = new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br');
        $first = $scraper->scrape(null);

        // Refaz o fake SEM detalhes.php — se o skip por fingerprint inalterado
        // falhar e o código tentar buscar detalhe mesmo assim, o
        // preventStrayRequests() acima faz essa chamada estourar aqui.
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
        ]);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_skips_a_product_whose_detail_page_cannot_be_parsed(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response('<html>sem dados</html>', 200),
        ]);

        $result = (new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br'))->scrape(null);

        $this->assertCount(0, $result->products);
    }

    public function test_retries_a_transient_failure_before_giving_up(): void
    {
        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::sequence()
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
                ], total: 1), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br'))->scrape(null);

        $this->assertCount(1, $result->products);
    }

    /**
     * Reproduz o problema real: 3 rodadas ao vivo morreram bem no meio da
     * listagem (conexão caindo mesmo depois de esgotar os 5 retries) e cada
     * uma reiniciava a paginação inteira do zero, perdendo tudo já coletado.
     * Um retry agora precisa continuar da página seguinte, não da 1ª.
     */
    public function test_resumes_listing_from_the_last_checkpointed_page_after_a_failure(): void
    {
        // Um único Http::fake() cobre as duas tentativas de propósito — chamar
        // Http::fake() de novo no meio do teste não REPÕE os stubs antigos
        // (eles continuam valendo junto dos novos), então page=2 continuaria
        // "falhando" mesmo tentando redefini-la como sucesso depois.
        Http::fake([
            // Sequência de 1 item de propósito (não uma resposta simples,
            // reutilizável) — se a retomada não pular a página 1 e tentar
            // buscá-la de novo, a sequência vazia estoura em vez de silenciosamente
            // deixar passar.
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=1*' => Http::sequence()
                ->push($this->listingPage([
                    ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
                ], total: 2), 200),
            'catweb.ms-motorservice.com.br*resultado.php*cw_pgAtual=2*' => Http::sequence()
                ->pushStatus(500)
                ->pushStatus(500)
                ->pushStatus(500)
                ->pushStatus(500)
                ->pushStatus(500)
                ->push($this->listingPage([
                    ['CodigoProduto' => 2, 'NumeroProduto' => 'X2'],
                ], total: 2), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::sequence()
                ->push($this->detailPage('X1'), 200)
                ->push($this->detailPage('X2'), 200),
        ]);

        $scraper = new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br');

        try {
            $scraper->scrape(null);
            $this->fail('Esperava uma exceção da falha na página 2.');
        } catch (\Throwable) {
            // esperado — todos os 5 retries de resultado.php?cw_pgAtual=2 falharam
        }

        $result = $scraper->scrape(null);

        $this->assertSame(['X1', 'X2'], array_column($result->products, 'codigo'));
    }

    /**
     * Mesma ideia, mas na etapa cara de detalhe por produto — a mais custosa
     * (~4300 requests num catálogo real), então a mais importante de não
     * refazer do zero numa falha no meio do caminho.
     */
    public function test_resumes_detail_fetching_from_the_last_checkpointed_product_after_a_failure(): void
    {
        Http::fake([
            // Sequência de 1 item de propósito — a listagem só deveria ser
            // buscada na 1ª tentativa; se o checkpoint de detalhe não bastar
            // pra pular a listagem inteira já concluída na retomada, a
            // sequência vazia estoura.
            'catweb.ms-motorservice.com.br*resultado.php*' => Http::sequence()
                ->push($this->listingPage([
                    ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
                    ['CodigoProduto' => 2, 'NumeroProduto' => 'X2'],
                ], total: 2), 200),
            // Detalhe do produto 1 com sucesso, depois o do produto 2 falhando
            // nas 5 tentativas (retry(5, 2000)) até esgotar de vez, e só então
            // uma resposta de sucesso pra ele — consumida na retomada.
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::sequence()
                ->push($this->detailPage('X1'), 200)
                ->pushStatus(500)
                ->pushStatus(500)
                ->pushStatus(500)
                ->pushStatus(500)
                ->pushStatus(500)
                ->push($this->detailPage('X2'), 200),
        ]);

        $scraper = new MsMotorserviceCatalogScraper('https://catweb.ms-motorservice.com.br');

        try {
            $scraper->scrape(null);
            $this->fail('Esperava uma exceção da falha no detalhe do produto 2.');
        } catch (\Throwable) {
            // esperado
        }

        // Se a retomada não pular o detalhe do produto 1 (já concluído) e
        // tentar refazê-lo, a sequência de detalhes.php ficaria fora de ordem
        // (devolveria a resposta esperada pro produto 2 antes da hora).
        $result = $scraper->scrape(null);

        $this->assertSame(['X1', 'X2'], array_column($result->products, 'codigo'));
    }

    /**
     * Um checkpoint de detalhe salvo contra uma listagem diferente (fingerprint
     * não bate) não pode ser reaproveitado — o catálogo pode ter mudado entre
     * a tentativa que falhou e essa, e os produtos "prontos" salvos não
     * corresponderiam mais aos de agora.
     */
    public function test_discards_a_stale_detail_checkpoint_from_a_different_listing_fingerprint(): void
    {
        $baseUrl = 'https://catweb.ms-motorservice.com.br';
        $hash = md5($baseUrl);

        Storage::disk('local')->put("catalogs/checkpoints/ms-motorservice-{$hash}-detail.json", json_encode([
            'products' => [['codigo' => 'STALE', 'descricao' => 'x', 'conversoes' => null, 'fabricante' => null, 'grupo' => null, 'subgrupo' => null, 'aplicacao' => null, 'imagem_url' => null]],
            'done' => [1],
            'fingerprint' => 'fingerprint-de-outra-listagem',
        ]));

        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        $result = (new MsMotorserviceCatalogScraper($baseUrl))->scrape(null);

        $this->assertSame(['X1'], array_column($result->products, 'codigo'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'detalhes.php'));
    }

    public function test_clears_checkpoints_after_a_successful_scrape(): void
    {
        $baseUrl = 'https://catweb.ms-motorservice.com.br';

        Http::fake([
            'catweb.ms-motorservice.com.br*resultado.php*' => Http::response($this->listingPage([
                ['CodigoProduto' => 1, 'NumeroProduto' => 'X1'],
            ], total: 1), 200),
            'catweb.ms-motorservice.com.br*detalhes.php*' => Http::response($this->detailPage('X1'), 200),
        ]);

        (new MsMotorserviceCatalogScraper($baseUrl))->scrape(null);

        $hash = md5($baseUrl);
        Storage::disk('local')->assertMissing("catalogs/checkpoints/ms-motorservice-{$hash}-listing.json");
        Storage::disk('local')->assertMissing("catalogs/checkpoints/ms-motorservice-{$hash}-detail.json");
    }
}
