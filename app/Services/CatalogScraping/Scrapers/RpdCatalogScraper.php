<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\Rpd\RpdProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes RPD's entire catalog (rpdborrachas.com.br), via RpdProductParser.
 * Submitting the search form with every filter blank
 * (pesquisa/montadora/aplicacao/grupo) returns the WHOLE catalog instead of
 * nothing, paginated at 50 parts/page via a `p` query
 * param — confirmed live: 1418 parts across 29 pages, with a "Total listado N"
 * counter on every page used the same way C123's `mTotPrd` is: to detect a
 * short/incomplete result and hard-fail rather than silently import a partial
 * catalog.
 *
 * Unlike C123 there's no cheap "last updated" marker to check before paying
 * for the full pagination — but unlike Kaer's per-part detail fetch, RPD's own
 * listing pages already carry every field needed (including cross-reference
 * codes, see RpdProductParser), so there's no separate expensive stage to
 * skip: the full traversal always runs, and only the resulting fingerprint
 * (sha256 of the sorted codigo list) decides whether to bother
 * storing/importing.
 */
class RpdCatalogScraper implements CatalogScraper
{
    private const int MAX_PAGES = 200;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $products = [];
        $total = null;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(10)
                ->retry(3, 500)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->get($this->baseUrl, [
                    'p' => $page,
                    'prolanc' => 'n',
                    'pesquisa' => '',
                    'montadora' => '',
                    'aplicacao' => '',
                    'grupo' => '',
                ])
                ->throw();

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            if ($total === null) {
                $total = $this->extractTotal($body);
            }

            $pageProducts = RpdProductParser::extractProducts($body);

            if ($pageProducts === []) {
                break;
            }

            $products = [...$products, ...$pageProducts];

            if ($total !== null && count($products) >= $total) {
                break;
            }
        }

        if ($total !== null && count($products) < $total) {
            throw new RuntimeException(sprintf(
                'RpdCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($products),
                $total
            ));
        }

        $source_version = $this->fingerprint($products);

        if ($source_version === $known_source_version) {
            return null;
        }

        return new ScrapedCatalog(
            source_version: $source_version,
            products: array_map(fn (array $product) => $this->toProductRow($product), $products),
        );
    }

    /**
     * @param  array{codigo: string, conversoes: ?array<int, string>, descricao: string, montadora: ?string, grupo: ?string, aplicacao: ?string, imagem_src: ?string}  $product
     * @return array{codigo: string, descricao: string, conversoes: ?array<int, string>, montadora: ?string, grupo: ?string, aplicacao: ?string, imagem_url: ?string}
     */
    private function toProductRow(array $product): array
    {
        return [
            'codigo' => $product['codigo'],
            'descricao' => $product['descricao'],
            'conversoes' => $product['conversoes'],
            'montadora' => $product['montadora'],
            'grupo' => $product['grupo'],
            'aplicacao' => $product['aplicacao'],
            'imagem_url' => $product['imagem_src'] !== null ? $this->baseUrl.'/'.ltrim($product['imagem_src'], '/') : null,
        ];
    }

    /**
     * @param  array<int, array{codigo: string}>  $products
     */
    private function fingerprint(array $products): string
    {
        $codigos = collect($products)->pluck('codigo')->sort()->implode('|');

        return hash('sha256', $codigos);
    }

    private function extractTotal(string $body): ?int
    {
        return preg_match('/Total\s+listado\s+(\d+)/iu', $body, $matches) === 1 ? (int) $matches[1] : null;
    }
}
