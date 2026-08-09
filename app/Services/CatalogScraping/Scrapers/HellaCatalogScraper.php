<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\Hella\HellaProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes Hella's entire catalog (catalogoexpresso.com.br/hella) — same
 * "Ideia2001/CatalogoExpresso" backend as MS Motorservice (identical
 * FabricantesAplicacao/ReferenciasCruzada data shapes, see
 * Services\Ideia2001\Ideia2001ProductFields), but served through a modern
 * Next.js frontend instead of MS Motorservice's legacy "cw" pages — see
 * Services\Hella\HellaProductParser for how the per-request React Server
 * Components ("RSC") payload is parsed (requesting any page/product URL with
 * an `RSC: 1` header returns just that flight payload, much lighter than a
 * full HTML page). `?pagina=N` paginates the listing (confirmed live: 849
 * parts, 10/page), with a `totalResultado` counter used the same way as
 * MS Motorservice's/C123's/RPD's own total markers: hard-fail on a short
 * listing rather than silently import a partial catalog.
 *
 * Same two-stage shape as Kaer/MS Motorservice: cheap listing pass first to
 * enumerate + fingerprint, expensive per-product detail fetch (needed for
 * conversões, only on the detail payload) skipped entirely when unchanged.
 * Pre-emptively uses the same generous timeout/retry as MS Motorservice
 * (timeout(20), retry(5, 2000)) since a real MS Motorservice run on this same
 * company's infrastructure hit sustained-traffic connection drops that a
 * shorter timeout/retry couldn't recover from — no confirmed incident here
 * yet, but the same backend is reason enough to expect the same risk.
 *
 * Product images share C123CatalogScraper's platform (c123.com.br/CatalogoExpresso/{id}/FotoProdWeb/dcp/),
 * under Hella's own numeric folder (confirmed live: 462 — matches this
 * catalog's own `codigoCatalogo` config value).
 */
class HellaCatalogScraper implements CatalogScraper
{
    private const string IMAGE_BASE_URL = 'https://www.c123.com.br/CatalogoExpresso/462/FotoProdWeb/dcp/';

    private const int MAX_PAGES = 500;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total] = $this->fetchListings();

        if ($total !== null && count($listings) < $total) {
            throw new RuntimeException(sprintf(
                'HellaCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($listings),
                $total
            ));
        }

        $source_version = $this->fingerprint($listings);

        if ($source_version === $known_source_version) {
            return null;
        }

        $products = [];

        foreach ($listings as $listing) {
            $product = $this->fetchDetail($listing['codigo_produto']);

            if ($product !== null) {
                $products[] = $product;
            }

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo_produto: int, codigo: string}>, 1: ?int}
     */
    private function fetchListings(): array
    {
        $listings = [];
        $total = null;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(20)
                ->retry(5, 2000)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeaders(['RSC' => '1'])
                ->get($this->baseUrl, ['pagina' => $page])
                ->throw();

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            if ($total === null) {
                $total = HellaProductParser::extractTotal($body);
            }

            $pageListings = HellaProductParser::extractListing($body);

            if ($pageListings === []) {
                break;
            }

            $listings = [...$listings, ...$pageListings];

            if ($total !== null && count($listings) >= $total) {
                break;
            }
        }

        return [$listings, $total];
    }

    /**
     * @return array{codigo: string, descricao: string, conversoes: ?array<int, string>, aplicacao: ?string, imagem_url: ?string}|null
     */
    private function fetchDetail(int $codigoProduto): ?array
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->withHeaders(['RSC' => '1'])
            ->get($this->baseUrl.'/produto/'.$codigoProduto)
            ->throw();

        $body = $response->body();
        unset($response);
        gc_collect_cycles();

        $product = HellaProductParser::extractDetail($body);

        if ($product === null) {
            return null;
        }

        return [
            'codigo' => $product['codigo'],
            'descricao' => $product['descricao'],
            'conversoes' => $product['conversoes'],
            'aplicacao' => $product['aplicacao'],
            'imagem_url' => $product['imagem_arquivo'] !== null ? self::IMAGE_BASE_URL.$product['imagem_arquivo'] : null,
        ];
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
