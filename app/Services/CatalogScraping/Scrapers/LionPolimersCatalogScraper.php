<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\LionPolimers\LionPolimersProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes Lion Polimers' catalog (lionpolimers.com) via its own
 * WooCommerce Store REST API (`/wp-json/wc/store/v1/products`) — see
 * LionPolimersProductParser for the JSON shape and field-naming quirks.
 *
 * Confirmed live: `per_page=100` (the platform's own cap) with the total
 * count coming from the `X-WP-Total` response header — a genuinely reliable,
 * exact total (unlike every scraper elsewhere in this app relying on the
 * source site's own "N produtos encontrados"-style counter, which is
 * sometimes off by a small margin) — confirmed reproducibly: 1787 total,
 * 18 full pages of 100 plus an 87-item last page, and page 19 returns an
 * empty array. Still keeps a small shortfall tolerance for safety/consistency
 * with every other scraper here, even though it's not expected to trigger.
 */
class LionPolimersCatalogScraper implements CatalogScraper
{
    protected const int MAX_PAGES = 200;

    private const int PER_PAGE = 100;

    private const int TOTAL_SHORTFALL_TOLERANCE = 5;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$products, $total, $reachedEmptyPage] = $this->fetchListings();

        if ($total === null) {
            throw new RuntimeException(sprintf(
                'LionPolimersCatalogScraper (%s): não foi possível determinar o total esperado de peças — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($products);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'LionPolimersCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($products),
                $total
            ));
        }

        $source_version = $this->fingerprint($products);

        if ($source_version === $known_source_version) {
            return null;
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo: string, descricao: string, grupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_url: ?string}>, 1: ?int, 2: bool}
     */
    private function fetchListings(): array
    {
        $products = [];
        $total = null;
        $reachedEmptyPage = false;

        for ($page = 1; $page <= static::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(20)
                ->retry(5, 2000)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->get($this->baseUrl.'/wp-json/wc/store/v1/products', ['per_page' => self::PER_PAGE, 'page' => $page])
                ->throw();

            if ($total === null) {
                $total = (int) $response->header('X-WP-Total');
                $total = $total > 0 ? $total : null;
            }

            $pageProducts = LionPolimersProductParser::extractListing($response->body());
            unset($response);
            gc_collect_cycles();

            if ($pageProducts === []) {
                $reachedEmptyPage = true;

                break;
            }

            $products = [...$products, ...$pageProducts];

            if ($total !== null && count($products) >= $total) {
                break;
            }
        }

        return [$products, $total, $reachedEmptyPage];
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
