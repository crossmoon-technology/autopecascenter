<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\MgFreios\MgFreiosProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes MG Freios' catalog (loja.mgfreios.com.br) — a Tray Commerce
 * retail storefront, a fundamentally different platform than every other
 * scraper in this app (not the "C123" ASP engine, not the "cw"/Ideia2001
 * engine): confirmed live, Tray conveniently embeds a Google Tag Manager
 * `dataLayer` JS array on both listing (busca.php) and product detail pages
 * with clean structured fields — see MgFreiosProductParser — so, unlike the
 * cw platform's product parser, no fragile positional parsing is needed for
 * codigo/descricao/montadora/grupo/subgrupo.
 *
 * The one thing that DOES need a per-product detail fetch: cross-reference/
 * OEM codes only appear inside the detail page's free-text description, not
 * anywhere in the listing. Same tradeoff as ATE/MS Motorservice: enumerate
 * cheaply via the listing first (confirmed live: 893 products across 60
 * pages, 15/page, "siteSearchResults" in that same dataLayer gives the site's
 * own reported total), fingerprint on that alone, and only pay for the ~900
 * detail requests when something actually changed.
 *
 * Confirmed live the site's WAF (Azion) 403s requests with an "incomplete"
 * User-Agent string (a bare "Mozilla/5.0 ... AppleWebKit/537.36" — enough for
 * the C123/cw sites elsewhere in this app — gets blocked here) — needs a
 * full, realistic browser UA string instead.
 */
class MgFreiosCatalogScraper implements CatalogScraper
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
                'MgFreiosCatalogScraper (%s): não foi possível determinar o total esperado de produtos — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($listings);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'MgFreiosCatalogScraper (%s): coletou apenas %d de %d produtos esperados — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
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
                'descricao' => $listing['descricao'],
                'conversoes' => $detail['conversoes'] ?? null,
                'fabricante' => $listing['fabricante'],
                'grupo' => $detail['grupo'] ?? null,
                'subgrupo' => $detail['subgrupo'] ?? null,
                'imagem_url' => $listing['imagem_url'],
            ];

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo: string, descricao: string, fabricante: ?string, imagem_url: ?string, url_produto: ?string}>, 1: ?int, 2: bool}
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
                ->get($this->baseUrl.'/loja/busca.php', ['palavra_busca' => '', 'pg' => $page])
                ->throw();

            $body = mb_convert_encoding($response->body(), 'UTF-8', 'ISO-8859-1');
            unset($response);
            gc_collect_cycles();

            ['listings' => $pageListings, 'total' => $pageTotal] = MgFreiosProductParser::extractListing($body);

            if ($total === null) {
                $total = $pageTotal;
            }

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
     * @return array{grupo: ?string, subgrupo: ?string, conversoes: ?array<int, string>}
     */
    private function fetchDetail(string $url): array
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent(self::USER_AGENT)
            ->get($url)
            ->throw();

        $body = mb_convert_encoding($response->body(), 'UTF-8', 'ISO-8859-1');
        unset($response);
        gc_collect_cycles();

        return MgFreiosProductParser::extractDetail($body);
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
