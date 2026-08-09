<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\UfiCatalogScraper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class UfiCatalogScraperTest extends TestCase
{
    /**
     * Um padrão de Http::fake() que não bate com a URL real cai pra rede de
     * verdade por padrão — preventStrayRequests() faz qualquer chamada não
     * coberta pelos fakes estourar aqui em vez de vazar pro site real.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Decodifica o corpo NDJSON de um _msearch (linha de header + linha de
     * query) do jeito que UfiCatalogScraper realmente monta.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function decodeMsearchBody(Request $request): array
    {
        $lines = explode("\n", trim($request->body()));

        return [json_decode($lines[0], true), json_decode($lines[1], true)];
    }

    /**
     * @param  array<int, array{0: array<string, mixed>, 1: array<int, string>}>  $hits  cada item é [source, sort]
     */
    private function msearchResponse(array $hits, ?int $total): string
    {
        return json_encode([
            'responses' => [[
                'hits' => [
                    'total' => ['value' => $total ?? 0, 'relation' => 'eq'],
                    'hits' => collect($hits)->map(fn (array $hit) => [
                        '_source' => $hit[0],
                        'sort' => $hit[1],
                    ])->all(),
                ],
            ]],
        ]);
    }

    private function filterDoc(string $code, array $overrides = []): array
    {
        return array_replace([
            'code' => $code,
            'family_name' => ['pt_BR' => "Descrição {$code}", 'en_EN' => "Description {$code}"],
            'application' => ['pt_BR' => 'FILTROS DE OLEO'],
            'shape' => ['pt_BR' => 'FILTRO ROSCADO'],
            'images' => ['image' => ['path' => "Filter/ufi/{$code}.jpg"]],
        ], $overrides);
    }

    private function crossDoc(string $filter, string $crossCode, string $manufacturer = 'VAG'): array
    {
        return [
            'filter' => $filter,
            'cross_code' => $crossCode,
            'manufacturer' => $manufacturer,
            'application' => ['pt_BR' => 'FILTROS DE OLEO'],
            'application_code' => 'oil',
            'description' => '',
        ];
    }

    public function test_scrapes_filters_and_cross_references_end_to_end(): void
    {
        Http::fake(function (Request $request) {
            [$header] = $this->decodeMsearchBody($request);

            return match ($header['index']) {
                'pim-br_filter_ufi' => Http::response($this->msearchResponse([
                    [$this->filterDoc('16.001.00'), [0]],
                ], total: 1), 200),
                'pim-br_cross_ufi' => Http::response($this->msearchResponse([
                    [$this->crossDoc('16.001.00', 'A1186', 'PURFLUX'), [0]],
                ], total: 1), 200),
            };
        });

        $result = (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        $this->assertCount(1, $result->products);
        $product = $result->products[0];
        $this->assertSame('16.001.00', $product['codigo']);
        $this->assertSame('Descrição 16.001.00', $product['descricao']);
        $this->assertSame('FILTROS DE OLEO', $product['grupo']);
        $this->assertSame('FILTRO ROSCADO', $product['subgrupo']);
        $this->assertSame(['A1186'], $product['conversoes']);
        $this->assertSame(
            'https://br.ufi-aftermarket.com/wp-content/plugins/ufi-search-wp-plugin/public/brazil/Filter/ufi/16.001.00.jpg',
            $product['imagem_url']
        );
        $this->assertNotNull($result->source_version);
    }

    public function test_sends_the_msearch_request_to_the_expected_endpoint(): void
    {
        Http::fake(function (Request $request) {
            [$header] = $this->decodeMsearchBody($request);

            return $header['index'] === 'pim-br_filter_ufi'
                ? Http::response($this->msearchResponse([[$this->filterDoc('16.001.00'), [0]]], total: 1), 200)
                : Http::response($this->msearchResponse([], total: 0), 200);
        });

        (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://br.ufi-aftermarket.com/wp-json/ufi-es/_msearch'
            && $request->method() === 'POST'
            && str_contains($request->header('Content-Type')[0] ?? '', 'ndjson'));
    }

    public function test_requests_track_total_hits_so_large_indices_report_their_real_total(): void
    {
        // Sem isso, o Elasticsearch trunca hits.total em 10000 (relation "gte") pra
        // qualquer índice maior — e o guard de completude do fetchAll() acharia, por
        // engano, que já tinha coletado tudo depois da primeira página.
        Http::fake(function (Request $request) {
            [, $query] = $this->decodeMsearchBody($request);

            $this->assertTrue($query['track_total_hits'] ?? false);

            return Http::response($this->msearchResponse([[$this->filterDoc('16.001.00'), [0]]], total: 1), 200);
        });

        (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);
    }

    public function test_falls_back_through_available_languages_when_family_name_has_no_pt_br(): void
    {
        Http::fake(function (Request $request) {
            [$header] = $this->decodeMsearchBody($request);

            return $header['index'] === 'pim-br_filter_ufi'
                ? Http::response($this->msearchResponse([[
                    $this->filterDoc('16.001.00', ['family_name' => ['en_EN' => 'Pressure valve']]),
                    [0],
                ]], total: 1), 200)
                : Http::response($this->msearchResponse([], total: 0), 200);
        });

        $result = (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        $this->assertSame('Pressure valve', $result->products[0]['descricao']);
    }

    public function test_returns_null_imagem_url_when_the_document_has_no_image(): void
    {
        Http::fake(function (Request $request) {
            [$header] = $this->decodeMsearchBody($request);

            return $header['index'] === 'pim-br_filter_ufi'
                ? Http::response($this->msearchResponse([[
                    $this->filterDoc('16.001.00', ['images' => ['image' => ['path' => null]]]),
                    [0],
                ]], total: 1), 200)
                : Http::response($this->msearchResponse([], total: 0), 200);
        });

        $result = (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        $this->assertNull($result->products[0]['imagem_url']);
    }

    public function test_deduplicates_cross_reference_codes_for_the_same_filter(): void
    {
        Http::fake(function (Request $request) {
            [$header] = $this->decodeMsearchBody($request);

            return match ($header['index']) {
                'pim-br_filter_ufi' => Http::response($this->msearchResponse([
                    [$this->filterDoc('16.001.00'), [0]],
                ], total: 1), 200),
                'pim-br_cross_ufi' => Http::response($this->msearchResponse([
                    [$this->crossDoc('16.001.00', 'A1186', 'PURFLUX'), [0]],
                    [$this->crossDoc('16.001.00', 'A1186', 'TECNOCAR'), [1]],
                ], total: 2), 200),
            };
        });

        $result = (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        $this->assertSame(['A1186'], $result->products[0]['conversoes']);
    }

    public function test_paginates_the_filter_index_via_search_after(): void
    {
        Http::fake(function (Request $request) {
            [$header, $query] = $this->decodeMsearchBody($request);

            if ($header['index'] === 'pim-br_cross_ufi') {
                return Http::response($this->msearchResponse([], total: 0), 200);
            }

            if (! isset($query['search_after'])) {
                return Http::response($this->msearchResponse([
                    [$this->filterDoc('16.001.00'), [0]],
                ], total: 2), 200);
            }

            $this->assertSame([0], $query['search_after']);

            return Http::response($this->msearchResponse([
                [$this->filterDoc('16.002.00'), [1]],
            ], total: 2), 200);
        });

        $result = (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        $this->assertCount(2, $result->products);
        $this->assertSame(['16.001.00', '16.002.00'], collect($result->products)->pluck('codigo')->all());
    }

    public function test_paginates_the_cross_reference_index_via_search_after(): void
    {
        Http::fake(function (Request $request) {
            [$header, $query] = $this->decodeMsearchBody($request);

            if ($header['index'] === 'pim-br_filter_ufi') {
                return Http::response($this->msearchResponse([
                    [$this->filterDoc('16.001.00'), [0]],
                ], total: 1), 200);
            }

            if (! isset($query['search_after'])) {
                return Http::response($this->msearchResponse([
                    [$this->crossDoc('16.001.00', 'A1186'), [0]],
                ], total: 2), 200);
            }

            return Http::response($this->msearchResponse([
                [$this->crossDoc('16.001.00', '6Q0201051'), [1]],
            ], total: 2), 200);
        });

        $result = (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        $this->assertSame(['A1186', '6Q0201051'], $result->products[0]['conversoes']);
    }

    public function test_throws_when_the_filter_index_falls_short_of_its_reported_total(): void
    {
        Http::fake(function (Request $request) {
            [$header, $query] = $this->decodeMsearchBody($request);

            if ($header['index'] === 'pim-br_cross_ufi') {
                return Http::response($this->msearchResponse([], total: 0), 200);
            }

            if (isset($query['search_after'])) {
                return Http::response($this->msearchResponse([], total: 5), 200);
            }

            return Http::response($this->msearchResponse([
                [$this->filterDoc('16.001.00'), [0]],
            ], total: 5), 200);
        });

        $this->expectException(RuntimeException::class);

        (new UfiCatalogScraper('https://br.ufi-aftermarket.com'))->scrape(null);

        Http::assertNotSent(fn (Request $request) => $this->decodeMsearchBody($request)[0]['index'] === 'pim-br_cross_ufi');
    }

    public function test_skips_the_cross_reference_stage_when_the_fingerprint_is_unchanged(): void
    {
        $crossRequests = 0;

        Http::fake(function (Request $request) use (&$crossRequests) {
            [$header] = $this->decodeMsearchBody($request);

            if ($header['index'] === 'pim-br_cross_ufi') {
                $crossRequests++;

                return Http::response($this->msearchResponse([
                    [$this->crossDoc('16.001.00', 'A1186'), [0]],
                ], total: 1), 200);
            }

            return Http::response($this->msearchResponse([
                [$this->filterDoc('16.001.00'), [0]],
            ], total: 1), 200);
        });

        $scraper = new UfiCatalogScraper('https://br.ufi-aftermarket.com');
        $first = $scraper->scrape(null);
        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
        $this->assertSame(1, $crossRequests);
    }
}
