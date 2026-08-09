<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\Basso3b\Basso3bProductParser;
use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use Illuminate\Support\Facades\Http;

/**
 * Bulk-scrapes Válvulas 3b/BBB's catalog (3bcatalogo.basso.com.ar) — see
 * Basso3bProductParser for the platform fingerprint and why a per-product
 * detail fetch is required (same two-stage shape as ATE/Kaer elsewhere in
 * this app: cheap listing pass to enumerate codigos, then one request per
 * product for descrição/aplicação/cross-reference).
 *
 * Confirmed live: 10 parts/page, 225 listing pages (2244 parts total, last
 * page has only 4), page 226 genuinely empty. The site has no total-count
 * marker anywhere on the page (unlike the "cw"/C123 platforms elsewhere in
 * this app) — same tradeoff as Kaer/RPD/Fania, so this trusts reaching a
 * genuine empty listing page with no separate total to validate against.
 */
class Basso3bCatalogScraper implements CatalogScraper
{
    private const int FAMILIA_ID = 102;

    protected const int MAX_PAGES = 400;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $listings = $this->fetchListings();
        $source_version = $this->fingerprint($listings);

        if ($source_version === $known_source_version) {
            return null;
        }

        $products = [];

        foreach ($listings as $listing) {
            $product = $this->fetchDetail($listing['id']);

            if ($product !== null) {
                $products[] = $product;
            }

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array<int, array{id: int, codigo: string}>
     */
    private function fetchListings(): array
    {
        $listings = [];

        for ($page = 1; $page <= static::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(20)
                ->retry(5, 2000)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->get($this->baseUrl.'/NroBasso', ['page' => $page, 'FamiliaId' => self::FAMILIA_ID])
                ->throw();

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            $pageListings = Basso3bProductParser::extractListing($body);

            if ($pageListings === []) {
                break;
            }

            $listings = [...$listings, ...$pageListings];
        }

        return $listings;
    }

    /**
     * @return array{codigo: string, descricao: string, grupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_url: ?string}|null
     */
    private function fetchDetail(int $id): ?array
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get($this->baseUrl.'/Articulo/Details/'.$id)
            ->throw();

        $body = $response->body();
        unset($response);
        gc_collect_cycles();

        $product = Basso3bProductParser::extractDetail($body);

        if ($product === null) {
            return null;
        }

        if ($product['imagem_url'] !== null) {
            $product['imagem_url'] = $this->baseUrl.$product['imagem_url'];
        }

        return $product;
    }

    /**
     * @param  array<int, array{codigo: string}>  $listings
     */
    private function fingerprint(array $listings): string
    {
        $codigos = collect($listings)->pluck('codigo')->sort()->implode('|');

        return hash('sha256', $codigos);
    }
}
