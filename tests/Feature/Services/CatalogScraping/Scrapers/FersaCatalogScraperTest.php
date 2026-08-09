<?php

namespace Tests\Feature\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\Scrapers\FersaCatalogScraper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Fersa's real Algolia index caps any single query at 1000 hits, so the
 * scraper partitions the catalog via facetFilters/numericFilters instead of
 * plain pagination (see FersaCatalogScraper's docblock). These tests fake a
 * small in-memory Algolia index (fakeAlgolia()) that implements just enough
 * of Algolia's real filtering semantics (facetFilters AND/negation,
 * numericFilters, facet counting) to exercise that recursive splitting for
 * real, rather than hand-scripting an exact sequence of canned responses.
 */
class FersaCatalogScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @param  array<int, array<string, mixed>>  $dataset
     */
    private function fakeAlgolia(array $dataset): void
    {
        Http::fake(function (Request $request) use ($dataset) {
            $body = $request->data();
            $facetFilters = $body['facetFilters'] ?? [];
            $numericFilters = $body['numericFilters'] ?? [];
            $hitsPerPage = $body['hitsPerPage'] ?? 0;
            $facets = $body['facets'] ?? [];

            $filtered = collect($dataset)->filter(function (array $hit) use ($facetFilters, $numericFilters) {
                foreach ($facetFilters as $filter) {
                    [$attribute, $value] = explode(':', $filter, 2);
                    $negate = str_starts_with($value, '-');
                    $value = $negate ? substr($value, 1) : $value;
                    $hitValues = (array) (data_get($hit, $attribute) ?? []);
                    $matches = in_array($value, $hitValues, true);

                    if ($negate ? $matches : ! $matches) {
                        return false;
                    }
                }

                foreach ($numericFilters as $numericFilter) {
                    preg_match('/^(\S+)\s*(>=|<=|<|>)\s*(-?[\d.]+)$/', $numericFilter, $m);
                    [, $attribute, $operator, $value] = $m;
                    $hitValue = data_get($hit, $attribute);

                    if ($hitValue === null) {
                        return false;
                    }

                    $value = (float) $value;
                    $ok = match ($operator) {
                        '>=' => $hitValue >= $value,
                        '<=' => $hitValue <= $value,
                        '<' => $hitValue < $value,
                        '>' => $hitValue > $value,
                    };

                    if (! $ok) {
                        return false;
                    }
                }

                return true;
            })->values();

            $response = ['nbHits' => $filtered->count(), 'hits' => $filtered->take($hitsPerPage)->all()];

            foreach ($facets as $facetAttribute) {
                $counts = [];

                foreach ($filtered as $hit) {
                    foreach ((array) (data_get($hit, $facetAttribute) ?? []) as $value) {
                        $counts[$value] = ($counts[$value] ?? 0) + 1;
                    }
                }

                $response['facets'][$facetAttribute] = $counts;
            }

            return Http::response($response);
        });
    }

    private function hit(string $objectID, string $manufacturer, ?string $category = null, ?float $diameter = null): array
    {
        return [
            'objectID' => $objectID,
            'name' => "Produto {$objectID}",
            'sku' => ["SKU-{$objectID}"],
            'manufacturer' => $manufacturer,
            'categories' => $category !== null ? ['level0' => ['Grupo'], 'level1' => [$category]] : [],
            'fersa_inner_diameter' => $diameter,
            'image_url' => null,
            'fersa_cross_references' => null,
            'fersa_cross_references_additional' => null,
        ];
    }

    public function test_scrapes_a_small_catalog_with_no_splitting_needed(): void
    {
        $this->fakeAlgolia([
            $this->hit('1', 'M1', 'Cat A'),
            $this->hit('2', 'M2', 'Cat B'),
        ]);

        $result = (new FersaCatalogScraper('https://brasil.fersa.com'))->scrape(null);

        $this->assertCount(2, $result->products);
        $this->assertSame(['SKU-1', 'SKU-2'], array_column($result->products, 'codigo'));
        $this->assertNotNull($result->source_version);
    }

    /**
     * Reproduz o caso real: um fabricante sozinho já passa do limite e
     * precisa ser fatiado por categoria.
     */
    public function test_splits_an_oversized_manufacturer_bucket_by_category(): void
    {
        $this->fakeAlgolia([
            $this->hit('1', 'M1', 'Cat A'),
            $this->hit('2', 'M1', 'Cat A'),
            $this->hit('3', 'M1', 'Cat B'),
        ]);

        $scraper = new class('https://brasil.fersa.com') extends FersaCatalogScraper
        {
            protected const int BUCKET_LIMIT = 2;
        };

        $result = $scraper->scrape(null);

        $this->assertCount(3, $result->products);
        $this->assertSame(['SKU-1', 'SKU-2', 'SKU-3'], array_column($result->products, 'codigo'));
    }

    /**
     * Reproduz o caso real da NKE: um bucket já fatiado por UMA categoria
     * específica continua acima do limite — precisa cair pra bisseção por
     * diâmetro, e NÃO pode tentar fatiar categories.level1 de novo (isso
     * bateria no mesmo único valor pra sempre e nunca terminaria).
     */
    public function test_falls_back_to_diameter_bisection_when_a_single_category_bucket_is_still_oversized(): void
    {
        $this->fakeAlgolia([
            $this->hit('1', 'M1', 'Cat A', diameter: 1),
            $this->hit('2', 'M1', 'Cat A', diameter: 2),
            $this->hit('3', 'M1', 'Cat A', diameter: 3),
            $this->hit('4', 'M1', 'Cat A', diameter: 4),
            $this->hit('5', 'M1', 'Cat A', diameter: 5),
        ]);

        $scraper = new class('https://brasil.fersa.com') extends FersaCatalogScraper
        {
            protected const int BUCKET_LIMIT = 2;

            protected const float DIAMETER_RANGE_MIN = 0;

            protected const float DIAMETER_RANGE_MAX = 8;
        };

        $result = $scraper->scrape(null);

        $this->assertCount(5, $result->products);
        $this->assertSame(['SKU-1', 'SKU-2', 'SKU-3', 'SKU-4', 'SKU-5'], collect($result->products)->pluck('codigo')->sort()->values()->all());
    }

    /**
     * Reproduz o "leftover" real (295 produtos da NKE sem categories.level1
     * nenhuma): o resto, depois de excluir todo valor de categoria visto,
     * tem que ser recuperado via negação, não descartado.
     */
    public function test_captures_products_without_any_category_via_negation(): void
    {
        $this->fakeAlgolia([
            $this->hit('1', 'M1', 'Cat A'),
            $this->hit('2', 'M1', 'Cat A'),
            $this->hit('3', 'M1', null),
        ]);

        $scraper = new class('https://brasil.fersa.com') extends FersaCatalogScraper
        {
            protected const int BUCKET_LIMIT = 2;
        };

        $result = $scraper->scrape(null);

        $this->assertSame(['SKU-1', 'SKU-2', 'SKU-3'], collect($result->products)->pluck('codigo')->sort()->values()->all());
    }

    public function test_throws_when_the_final_collected_count_does_not_match_the_expected_total(): void
    {
        // Simula uma inconsistência: o índice reporta um total maior do que
        // o que qualquer consulta realmente devolve.
        Http::fake(function (Request $request) {
            $body = $request->data();

            if (isset($body['facets']) && ! isset($body['facetFilters'])) {
                return Http::response(['nbHits' => 1000, 'hits' => [], 'facets' => ['manufacturer' => ['M1' => 1000]]]);
            }

            if (! isset($body['facetFilters']) && ! isset($body['facets'])) {
                return Http::response(['nbHits' => 1000, 'hits' => []]);
            }

            return Http::response(['nbHits' => 1, 'hits' => [['objectID' => '1', 'sku' => ['SKU-1']]]]);
        });

        $this->expectException(RuntimeException::class);

        (new FersaCatalogScraper('https://brasil.fersa.com'))->scrape(null);
    }

    /**
     * Reproduz os 3 produtos reais que nenhuma bisseção numérica alcança
     * (nenhum atributo físico populado) — uma pequena diferença é tolerada
     * em vez de travar a raspagem inteira por causa de um punhado de
     * registros permanentemente inacessíveis via essa API.
     */
    public function test_tolerates_a_small_shortfall(): void
    {
        $this->fakeAlgolia([
            $this->hit('1', 'M1', 'Cat A'),
            $this->hit('2', 'M1', 'Cat A'),
        ]);

        Http::fake(function (Request $request) {
            $body = $request->data();

            // O count() global reporta 3, mas só existem 2 hits de verdade —
            // simula os 3 produtos reais que nenhum filtro alcança.
            if (! isset($body['facetFilters']) && ! isset($body['facets'])) {
                return Http::response(['nbHits' => 3, 'hits' => []]);
            }

            if (isset($body['facets']) && ! isset($body['facetFilters'])) {
                return Http::response(['nbHits' => 2, 'hits' => [], 'facets' => ['manufacturer' => ['M1' => 2]]]);
            }

            return Http::response(['nbHits' => 2, 'hits' => [
                ['objectID' => '1', 'sku' => ['SKU-1']],
                ['objectID' => '2', 'sku' => ['SKU-2']],
            ]]);
        });

        $result = (new FersaCatalogScraper('https://brasil.fersa.com'))->scrape(null);

        $this->assertCount(2, $result->products);
    }

    public function test_throws_when_there_are_no_products_at_all(): void
    {
        $this->fakeAlgolia([]);

        $this->expectException(RuntimeException::class);

        (new FersaCatalogScraper('https://brasil.fersa.com'))->scrape(null);
    }

    public function test_skips_entirely_when_the_fingerprint_is_unchanged(): void
    {
        $this->fakeAlgolia([
            $this->hit('1', 'M1', 'Cat A'),
        ]);

        $scraper = new FersaCatalogScraper('https://brasil.fersa.com');
        $first = $scraper->scrape(null);

        $second = $scraper->scrape($first->source_version);

        $this->assertNull($second);
    }

    public function test_sends_algolia_credentials_as_headers(): void
    {
        $this->fakeAlgolia([
            $this->hit('1', 'M1', 'Cat A'),
        ]);

        (new FersaCatalogScraper('https://brasil.fersa.com'))->scrape(null);

        Http::assertSent(fn (Request $request) => str_contains(strtolower($request->url()), 'k0zlp23dnd-dsn.algolia.net')
            && $request->hasHeader('X-Algolia-Application-Id', 'K0ZLP23DND')
            && $request->hasHeader('X-Algolia-API-Key'));
    }
}
