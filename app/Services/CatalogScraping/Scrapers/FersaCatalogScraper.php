<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\Fersa\FersaProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes Fersa Brasil's catalog (brasil.fersa.com) — powered entirely
 * by Algolia (confirmed live: both the search box and the category browsing
 * pages are Algolia InstantSearch widgets, no server-rendered product grid
 * exists at all). Queries Algolia's REST API directly with the SAME public,
 * search-only credentials the site's own JS embeds in every page load (see
 * the constants below) — not a private/credentialed endpoint, same category
 * as UFI's Elasticsearch proxy or ZM's token-server field elsewhere in this
 * app. `$baseUrl` (the storefront URL, from config/scrapers.php) is kept
 * only to satisfy the CatalogScraper interface — every request here goes
 * straight to Algolia's own API host, unrelated to it.
 *
 * The real challenge: Algolia's `query` endpoint hard-caps pagination at
 * 1000 total results per query (confirmed live: requesting page 1 at
 * hitsPerPage=1000 returns an explicit "you can only fetch the 1000 hits
 * for this query" error) — and the `browse` endpoint, which has no such cap,
 * returns 403 with this search-only key. With 26016 products total, a
 * single query can never reach them all. The standard workaround — and
 * what fetchBucket() below does — is to partition the catalog into many
 * smaller queries via facet filters, each kept under the 1000 cap:
 *
 * 1. `manufacturer` (confirmed live: 4 values — NKE, A&S, PFI, FERSA — each
 *    already over 1000 alone, so this alone is never sufficient).
 * 2. `categories.level1` within each manufacturer (confirmed live: most
 *    combinations land well under 1000, but not all — e.g. NKE's own
 *    "Rolamentos de Rolos Cilíndricos Padrão" bucket alone is 3312).
 * 3. For whatever's still over 1000 after that: bisecting a numeric range
 *    on `fersa_inner_diameter` (confirmed live: filterable via
 *    `numericFilters` even though it isn't in the site's own declared UI
 *    facet list) — halving the range until every sub-bucket clears 1000.
 *
 * Categories aren't populated on every product (confirmed live: NKE alone
 * has 295 products with no `categories.level1` value at all) — Algolia has
 * no "attribute is missing" filter, so those can't be fetched by excluding
 * every known category value... except they CAN: facetFilters supports
 * negation (`"categories.level1:-SomeValue"`, confirmed live the math works
 * out exactly), so excluding every category value seen within a bucket
 * yields precisely the leftover with none of them (missing entirely or
 * some other value not yet split off) — that leftover then recurses through
 * the exact same splitting logic.
 *
 * `fersa_inner_diameter` bisection has no such negation trick available
 * (numeric filters always exclude records missing the attribute, in either
 * direction), and this one's a real gap, not just theoretical: confirmed
 * live, exactly 3 of the 26016 products (all under NKE's "Rolamentos
 * Radiais de Esferas") have NO numeric physical attribute set at all —
 * not inner/outer diameter, height, or weight, ruling out falling back to
 * a different attribute — and don't surface in the first 1000 hits of any
 * of the index's five sort-order replicas either, so there's no ordering
 * trick that reaches them within the same 1000-hit cap. This is a genuine
 * Algolia search-API limitation (there's no "attribute is missing" filter),
 * not a bug here. Treated the same way every other scraper in this app
 * treats a small, confirmed source-side gap: TOTAL_SHORTFALL_TOLERANCE
 * allows it through; anything bigger still hard-fails (see scrape()) rather
 * than silently importing something more incomplete than that.
 */
class FersaCatalogScraper implements CatalogScraper
{
    private const string APP_ID = 'K0ZLP23DND';

    /**
     * Public Algolia search-only key, embedded in brasil.fersa.com's own
     * page JS on every load (a base64-encoded Algolia "secured API key",
     * signature + empty tagFilters restriction) — not a credential obtained
     * out-of-band. See class docblock.
     */
    private const string API_KEY = 'MzAzMmYwMjZkNTM0MzUyM2U3ZTQ0YmI2MzVmZDRmNmYwNTU0ZTRiZjE2ODdkYzJmNzYxMDRhODJhOTlmZGNjM3RhZ0ZpbHRlcnM9';

    private const string INDEX = 'nke_pro_fersa_brazil_pt_products';

    private const string DIAMETER_ATTRIBUTE = 'fersa_inner_diameter';

    protected const int BUCKET_LIMIT = 1000;

    /**
     * Confirmed live: exactly 3 products are permanently unreachable via
     * numeric-attribute bisection (see class docblock) — this covers that
     * specific, understood gap without opening the door to a much bigger,
     * unnoticed one.
     */
    private const int TOTAL_SHORTFALL_TOLERANCE = 10;

    protected const float DIAMETER_RANGE_MIN = 0;

    protected const float DIAMETER_RANGE_MAX = 100_000;

    private const int MAX_VALUES_PER_FACET = 1000;

    private const int REQUEST_DELAY_MICROSECONDS = 100_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $totalExpected = $this->count([]);

        if ($totalExpected === 0) {
            throw new RuntimeException('FersaCatalogScraper: não foi possível determinar o total esperado de produtos — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.');
        }

        $manufacturers = array_keys($this->facetCounts([], 'manufacturer'));

        if ($manufacturers === []) {
            throw new RuntimeException('FersaCatalogScraper: não foi possível obter a lista de fabricantes — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.');
        }

        $hits = [];

        foreach ($manufacturers as $manufacturer) {
            $hits = [...$hits, ...$this->fetchBucket([$this->facetFilter('manufacturer', $manufacturer)])];
        }

        $uniqueHits = collect($hits)->unique('objectID')->values();
        $shortfall = $totalExpected - $uniqueHits->count();

        if ($shortfall > self::TOTAL_SHORTFALL_TOLERANCE) {
            throw new RuntimeException(sprintf(
                'FersaCatalogScraper: coletou %d de %d produtos esperados — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $uniqueHits->count(),
                $totalExpected
            ));
        }

        $products = $uniqueHits
            ->map(fn (array $hit) => FersaProductParser::extractProduct($hit))
            ->filter()
            ->values()
            ->all();

        $source_version = $this->fingerprint($products);

        if ($source_version === $known_source_version) {
            return null;
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * $skipCategorySplit avoids an infinite loop: a bucket already narrowed
     * to one SPECIFIC categories.level1 value that's STILL over the limit
     * (confirmed live: NKE's "Rolamentos de Rolos Cilíndricos Padrão" alone
     * is 3312) would otherwise re-query that same attribute and get back
     * that exact same single value again, forever. The "remainder" bucket
     * (built by negating every value just seen) is exempt from this — it
     * legitimately needs to check categories.level1 again, and by
     * construction gets back an empty facet breakdown there (every value
     * that could show up was already negated), which is what lets it fall
     * through to diameter bisection instead of looping.
     *
     * @param  array<int, string>  $facetFilters
     * @return array<int, array<string, mixed>>
     */
    private function fetchBucket(array $facetFilters, bool $skipCategorySplit = false): array
    {
        $count = $this->count($facetFilters);

        if ($count === 0) {
            return [];
        }

        if ($count <= static::BUCKET_LIMIT) {
            return $this->fetchDirect($facetFilters);
        }

        if (! $skipCategorySplit) {
            $categoryCounts = $this->facetCounts($facetFilters, 'categories.level1');

            if ($categoryCounts !== []) {
                $hits = [];

                foreach (array_keys($categoryCounts) as $category) {
                    $hits = [...$hits, ...$this->fetchBucket([...$facetFilters, $this->facetFilter('categories.level1', $category)], skipCategorySplit: true)];
                }

                $excludeAll = collect(array_keys($categoryCounts))
                    ->map(fn (string $category) => $this->facetFilter('categories.level1', $category, negate: true))
                    ->all();

                // O "resto" (sem nenhum dos valores de categoria vistos acima —
                // seja por não ter categories.level1 nenhuma, seja por ter um
                // valor que não apareceu no facet breakdown por algum motivo)
                // passa pela MESMA lógica recursiva, não um caso especial.
                $hits = [...$hits, ...$this->fetchBucket([...$facetFilters, ...$excludeAll])];

                return $hits;
            }
        }

        return $this->fetchByDiameterBisection($facetFilters, static::DIAMETER_RANGE_MIN, static::DIAMETER_RANGE_MAX);
    }

    /**
     * @param  array<int, string>  $facetFilters
     * @return array<int, array<string, mixed>>
     */
    private function fetchByDiameterBisection(array $facetFilters, float $min, float $max): array
    {
        $numericFilters = [
            self::DIAMETER_ATTRIBUTE.' >= '.$min,
            self::DIAMETER_ATTRIBUTE.' <= '.$max,
        ];

        $count = $this->count($facetFilters, $numericFilters);

        if ($count === 0) {
            return [];
        }

        if ($count <= static::BUCKET_LIMIT) {
            return $this->fetchDirect($facetFilters, $numericFilters);
        }

        if ($max - $min < 0.001) {
            throw new RuntimeException(sprintf(
                'FersaCatalogScraper: bucket com %d produtos não pôde ser subdividido por diâmetro (intervalo esgotado) — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $count
            ));
        }

        $mid = ($min + $max) / 2;

        return [
            ...$this->fetchByDiameterBisection($facetFilters, $min, $mid),
            ...$this->fetchByDiameterBisection($facetFilters, $mid, $max),
        ];
    }

    /**
     * @param  array<int, string>  $facetFilters
     */
    private function count(array $facetFilters, array $numericFilters = []): int
    {
        return (int) ($this->query($facetFilters, $numericFilters, hitsPerPage: 0)['nbHits'] ?? 0);
    }

    /**
     * @param  array<int, string>  $facetFilters
     * @return array<string, int>
     */
    private function facetCounts(array $facetFilters, string $facetAttribute): array
    {
        $response = $this->query($facetFilters, [], hitsPerPage: 0, facets: [$facetAttribute]);

        return $response['facets'][$facetAttribute] ?? [];
    }

    /**
     * @param  array<int, string>  $facetFilters
     * @return array<int, array<string, mixed>>
     */
    private function fetchDirect(array $facetFilters, array $numericFilters = []): array
    {
        return $this->query($facetFilters, $numericFilters, hitsPerPage: static::BUCKET_LIMIT)['hits'] ?? [];
    }

    /**
     * @param  array<int, string>  $facetFilters
     * @param  array<int, string>  $numericFilters
     * @param  array<int, string>  $facets
     * @return array<string, mixed>
     */
    private function query(array $facetFilters, array $numericFilters, int $hitsPerPage, array $facets = []): array
    {
        usleep(self::REQUEST_DELAY_MICROSECONDS);

        $body = ['query' => '', 'hitsPerPage' => $hitsPerPage];

        if ($facetFilters !== []) {
            $body['facetFilters'] = $facetFilters;
        }

        if ($numericFilters !== []) {
            $body['numericFilters'] = $numericFilters;
        }

        if ($facets !== []) {
            $body['facets'] = $facets;
            $body['maxValuesPerFacet'] = self::MAX_VALUES_PER_FACET;
        }

        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withHeaders([
                'X-Algolia-Application-Id' => self::APP_ID,
                'X-Algolia-API-Key' => self::API_KEY,
            ])
            ->post('https://'.self::APP_ID.'-dsn.algolia.net/1/indexes/'.self::INDEX.'/query', $body)
            ->throw();

        return $response->json();
    }

    private function facetFilter(string $attribute, string $value, bool $negate = false): string
    {
        return $attribute.':'.($negate ? '-' : '').$value;
    }

    /**
     * @param  array<int, array{codigo: string}>  $products
     */
    private function fingerprint(array $products): string
    {
        $codigos = collect($products)->pluck('codigo')->sort()->implode('|');

        return hash('sha256', $codigos);
    }
}
