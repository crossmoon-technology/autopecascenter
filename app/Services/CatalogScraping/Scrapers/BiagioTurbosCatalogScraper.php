<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\BiagioTurbos\BiagioTurbosProductParser;
use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bulk-scrapes Biagio Turbos' catalog (catalogo.biagioturbos.com.br) — see
 * BiagioTurbosProductParser for the platform fingerprint and JSON shape.
 *
 * Unlike every other scraper in this app, there's no listing page/endpoint to
 * paginate at all — the site's own API only exposes `/api/turbos/{id}`, so
 * this enumerates turbo ids sequentially (confirmed live: dense from 1 to
 * 509, a handful of internal 404 gaps for deleted records — e.g. 371, 374,
 * 409 — so a single 404 must NOT stop the scan; only a long run of
 * consecutive misses, past the point at least one turbo has already been
 * found, means the real end of the range was reached).
 *
 * Laravel's `retry()` treats ANY non-2xx response as a failure worth
 * retrying by default (confirmed via tinker: a plain 404 gets retried the
 * full attempt count and then throws, even with no explicit ->throw() call)
 * — which would waste 5x the requests on every legitimate gap id, so this
 * passes a `$when` callback that only retries connection failures/5xx, not a
 * 404, plus `throw: false` so a real 404 comes back as an ordinary response
 * to inspect instead of an exception.
 */
class BiagioTurbosCatalogScraper implements CatalogScraper
{
    protected const int MAX_ID = 2000;

    private const int CONSECUTIVE_MISS_TOLERANCE = 30;

    private const int REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $products = $this->fetchProducts();

        $source_version = $this->fingerprint($products);

        if ($source_version === $known_source_version) {
            return null;
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array<int, array{codigo: string, descricao: string, grupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_url: ?string}>
     */
    private function fetchProducts(): array
    {
        $products = [];
        $consecutiveMisses = 0;

        for ($id = 1; $id <= static::MAX_ID; $id++) {
            if ($id > 1) {
                usleep(self::REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(20)
                ->retry(5, 2000, self::retryUnlessNotFound(...), throw: false)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->get($this->baseUrl.'/api/turbos/'.$id);

            if ($response->status() === 404) {
                if ($products !== []) {
                    $consecutiveMisses++;

                    if ($consecutiveMisses >= self::CONSECUTIVE_MISS_TOLERANCE) {
                        break;
                    }
                }

                continue;
            }

            $response->throw();
            $consecutiveMisses = 0;

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            $product = BiagioTurbosProductParser::extractDetail($body, $this->baseUrl);

            if ($product !== null) {
                $products[] = $product;
            }
        }

        return $products;
    }

    private static function retryUnlessNotFound(Throwable $exception): bool
    {
        return ! ($exception instanceof RequestException && $exception->response->status() === 404);
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
