<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\MgPecasAutomotivas\MgPecasAutomotivasProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes MG Peças Automotivas' catalog (mgpecasautomotivas.com.br) —
 * a Nuvemshop (Tiendanube) storefront, confirmed live via its own JS
 * (`LS.*` globals) and the schema.org JSON-LD every page embeds. The
 * listing (`/produtos?page=N`) gives codigo/imagem/detail-URL cheaply
 * (confirmed live: 1366 products, 50/page, exactly 0 hits on the genuine
 * first empty page past the end — unlike Roltens elsewhere in this app,
 * this platform does NOT clamp to the last page); cross-reference/OEM codes
 * and vehicle application only live in a rich-text "Descrição" panel on
 * each product's own detail page (see MgPecasAutomotivasProductParser), so
 * — same two-stage shape as ATE/MS Motorservice — this fingerprints on the
 * cheap listing pass alone before paying for ~1366 detail requests.
 */
class MgPecasAutomotivasCatalogScraper implements CatalogScraper
{
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    protected const int MAX_PAGES = 200;

    private const int TOTAL_SHORTFALL_TOLERANCE = 5;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total, $reachedEmptyPage] = $this->fetchListings();

        if ($total === null) {
            throw new RuntimeException(sprintf(
                'MgPecasAutomotivasCatalogScraper (%s): não foi possível determinar o total esperado de produtos — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($listings);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'MgPecasAutomotivasCatalogScraper (%s): coletou apenas %d de %d produtos esperados — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
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
            $detail = $listing['url_produto'] !== null ? $this->fetchDetail($listing['url_produto']) : null;

            $products[] = [
                'codigo' => $listing['codigo'],
                'descricao' => $detail['descricao'] ?? $listing['codigo'],
                'conversoes' => $detail['conversoes'] ?? null,
                'fabricante' => $detail['fabricante'] ?? null,
                'grupo' => $detail['grupo'] ?? null,
                'aplicacao' => $detail['aplicacao'] ?? null,
                'imagem_url' => $listing['imagem_url'],
            ];

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo: string, imagem_url: ?string, url_produto: ?string}>, 1: ?int, 2: bool}
     */
    private function fetchListings(): array
    {
        $listings = [];
        $total = null;
        $reachedEmptyPage = false;

        for ($page = 1; $page <= static::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(20)
                ->retry(5, 2000)
                ->withUserAgent(self::USER_AGENT)
                ->get($this->baseUrl.'/produtos', ['page' => $page])
                ->throw();

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            if ($total === null) {
                $total = MgPecasAutomotivasProductParser::extractTotal($body);
            }

            $pageListings = MgPecasAutomotivasProductParser::extractListing($body);

            if ($pageListings === []) {
                $reachedEmptyPage = true;

                break;
            }

            $listings = [...$listings, ...$pageListings];

            if ($total !== null && count($listings) >= $total) {
                break;
            }
        }

        return [$listings, $total, $reachedEmptyPage];
    }

    /**
     * @return array{descricao: ?string, grupo: ?string, fabricante: ?string, conversoes: ?array<int, string>, aplicacao: ?string}
     */
    private function fetchDetail(string $url): array
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent(self::USER_AGENT)
            ->get($url)
            ->throw();

        $body = $response->body();
        unset($response);
        gc_collect_cycles();

        return MgPecasAutomotivasProductParser::extractDetail($body);
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
