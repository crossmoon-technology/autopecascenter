<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

/**
 * Bulk-scrapes UFI's Brazilian catalog (br.ufi-aftermarket.com) — not a page
 * scrape at all, unlike every other scraper in this app: the site's own
 * search app (a React app, "ufi-es") talks directly to a real Elasticsearch
 * cluster through a WordPress plugin that transparently proxies `_msearch`
 * calls at `wp-json/ufi-es/_msearch`, with no authentication (confirmed
 * live). Two indices matter here: `pim-br_filter_ufi` (the actual product
 * catalog — confirmed live at exactly 5285 documents, small enough to fetch
 * in a single page) and `pim-br_cross_ufi` (OEM/competitor cross-reference
 * codes, one document per filter+equivalent pair — confirmed live at 159261
 * documents). Both are fetched as a handful of large bulk pages instead of
 * one request per product like every other scraper here — the source itself
 * just happens to make that possible.
 *
 * Pagination sorts by `_doc` and uses `search_after` instead of plain
 * `from`/`size`: Elasticsearch caps `from+size` at `index.max_result_window`
 * (10000 by default), and the cross-reference index alone is already well
 * past that. `search_after` has no such ceiling since it never looks back
 * past the previous page's own sort values.
 *
 * Product images resolve to
 * `{baseUrl}/wp-content/plugins/ufi-search-wp-plugin/public/brazil/{path}`
 * (confirmed live) — reverse-engineered from the search app's own bundled JS
 * (its image-URL-building helper), since the ES document only stores the
 * path relative to that plugin's own public folder, and the "brazil" segment
 * is this scraper's fixed region (`window.ufiAppSettings.region` on the real
 * site), not something derivable from the document itself.
 *
 * `family_name` (used for `descricao`) isn't consistently translated —
 * plenty of documents have no `pt_BR` key at all, only a handful of other
 * languages — so it falls back through `en_EN`/`it_IT`/whatever key exists
 * rather than risk an empty description.
 */
class UfiCatalogScraper implements CatalogScraper
{
    private const string SEARCH_ENDPOINT = '/wp-json/ufi-es/_msearch';

    private const string FILTER_INDEX = 'pim-br_filter_ufi';

    private const string CROSS_INDEX = 'pim-br_cross_ufi';

    private const string IMAGE_BASE_URL_SUFFIX = '/wp-content/plugins/ufi-search-wp-plugin/public/brazil/';

    private const int PAGE_SIZE = 10000;

    private const int MAX_PAGES = 50;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 200_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $filters = $this->fetchAll(self::FILTER_INDEX);
        $source_version = $this->fingerprint($filters);

        if ($source_version === $known_source_version) {
            return null;
        }

        $crossReferences = $this->fetchCrossReferences();

        $products = collect($filters)
            ->map(fn (array $doc) => $this->buildProduct($doc, $crossReferences[(string) ($doc['code'] ?? '')] ?? []))
            ->all();

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array<string, array<int, string>> código UFI => códigos equivalentes
     */
    private function fetchCrossReferences(): array
    {
        $map = [];

        foreach ($this->fetchAll(self::CROSS_INDEX) as $doc) {
            $filterCode = (string) ($doc['filter'] ?? '');
            $crossCode = trim((string) ($doc['cross_code'] ?? ''));

            if ($filterCode === '' || $crossCode === '') {
                continue;
            }

            // Chave de array pra dedupe barato — várias marcas concorrentes podem
            // repetir o mesmo código equivalente pro mesmo filtro.
            $map[$filterCode][$crossCode] = true;
        }

        return collect($map)->map(fn (array $codes) => array_keys($codes))->all();
    }

    /**
     * @param  array<int, string>  $conversoes
     * @return array{codigo: string, descricao: string, grupo: ?string, subgrupo: ?string, conversoes: ?array<int, string>, imagem_url: ?string}
     */
    private function buildProduct(array $doc, array $conversoes): array
    {
        $familyName = is_array($doc['family_name'] ?? null) ? $doc['family_name'] : [];
        $descricao = $familyName['pt_BR'] ?? $familyName['en_EN'] ?? $familyName['it_IT'] ?? array_values($familyName)[0] ?? '';

        $imagePath = $doc['images']['image']['path'] ?? null;

        return [
            'codigo' => (string) ($doc['code'] ?? ''),
            'descricao' => (string) $descricao,
            'grupo' => $doc['application']['pt_BR'] ?? null,
            'subgrupo' => $doc['shape']['pt_BR'] ?? null,
            'conversoes' => $conversoes !== [] ? $conversoes : null,
            'imagem_url' => $imagePath !== null ? $this->baseUrl.self::IMAGE_BASE_URL_SUFFIX.$imagePath : null,
        ];
    }

    /**
     * Fetches every document of the given index via search_after pagination,
     * hard-failing if the source's own reported total was never actually
     * reached — same completeness guard every other scraper in this app
     * applies, just against Elasticsearch's own hit count instead of a
     * "N produtos" label scraped out of HTML.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $index): array
    {
        $documents = [];
        $searchAfter = null;
        $total = null;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $query = [
                'query' => ['match_all' => new stdClass],
                'size' => self::PAGE_SIZE,
                'sort' => [['_doc' => 'asc']],
                // Elasticsearch só conta o total de verdade além de 10000 se isso for
                // pedido explicitamente — sem isso, `hits.total` vem como {"value":10000,
                // "relation":"gte"} pra qualquer índice com mais que isso (confirmado ao
                // vivo: pim-br_cross_ufi tem 159261 documentos), e o guard de completude
                // abaixo pararia de paginar cedo demais, achando que já tinha tudo.
                'track_total_hits' => true,
            ];

            if ($searchAfter !== null) {
                $query['search_after'] = $searchAfter;
            }

            $result = $this->msearch($index, $query);
            $hits = $result['hits']['hits'] ?? [];

            if ($hits === []) {
                break;
            }

            $total ??= $result['hits']['total']['value'] ?? null;

            foreach ($hits as $hit) {
                $documents[] = $hit['_source'];
            }

            $searchAfter = end($hits)['sort'];

            if ($total !== null && count($documents) >= $total) {
                break;
            }
        }

        if ($total !== null && count($documents) < $total) {
            throw new RuntimeException(sprintf(
                'UfiCatalogScraper (%s): coletou apenas %d de %d documentos esperados do índice "%s" — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($documents),
                $total,
                $index
            ));
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function msearch(string $index, array $query): array
    {
        $body = json_encode(['index' => $index])."\n".json_encode($query)."\n";

        $response = Http::timeout(60)
            ->retry(3, 2000)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->withBody($body, 'application/x-ndjson')
            ->post($this->baseUrl.self::SEARCH_ENDPOINT)
            ->throw();

        $decoded = $response->json();
        unset($response);
        gc_collect_cycles();

        return $decoded['responses'][0] ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filters
     */
    private function fingerprint(array $filters): string
    {
        $codigos = collect($filters)->pluck('code')->sort()->implode('|');

        return hash('sha256', $codigos);
    }
}
