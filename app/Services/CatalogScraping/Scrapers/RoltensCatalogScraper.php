<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\Roltens\RoltensProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes Roltens' catalog — split across two hostnames, confirmed
 * live: the paginated listing lives on catalogo.roltens.com.br (a jQuery
 * bootgrid JSON API, `/produtos/listagem?page=N`, confirmed live: 1306
 * products, 25/page), while cross-reference/OEM codes only exist on each
 * product's own detail page, on the DIFFERENT roltens.com.br hostname
 * (`/produto/{codigo}`) — the listing itself carries no cross-reference at
 * all, so (unlike IKS) a per-product detail fetch is unavoidable here. The
 * listing conveniently hands back each product's full absolute detail URL
 * (`saibamais`), so this never has to construct one from a slug/base URL.
 *
 * Unlike every other paginated source in this app, requesting a page past
 * the real last one does NOT return an empty result — it just re-returns the
 * LAST real page's rows again (confirmed live: page 53 and page 54, one
 * past the real last page, return the exact same 6 rows). So there's no
 * "reached a genuine empty page" signal to lean on for a shortfall
 * tolerance (see C123CatalogScraper/AteCatalogScraper for that pattern) —
 * completeness here is judged purely against the listing API's own `total`
 * field, which pagination is stopped at exactly (`count(listings) >= total`)
 * rather than ever intentionally overrun.
 *
 * No cheap "did it change" marker exists either (same tradeoff as Kaer/RPD/
 * Fania/MG Freios/ZM): always fetch the full listing, then fingerprint the
 * sorted codigo list to decide whether the (expensive, ~1306-request) detail
 * stage is even worth doing.
 */
class RoltensCatalogScraper implements CatalogScraper
{
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    protected const int MAX_PAGES = 200;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total] = $this->fetchListings();

        if ($total === null) {
            throw new RuntimeException(sprintf(
                'RoltensCatalogScraper (%s): não foi possível determinar o total esperado de produtos — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        if (count($listings) < $total) {
            throw new RuntimeException(sprintf(
                'RoltensCatalogScraper (%s): coletou apenas %d de %d produtos esperados — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
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
                'grupo' => $listing['grupo'],
                'aplicacao' => $detail['aplicacao'] ?? null,
                'imagem_url' => $listing['imagem_url'],
            ];

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo: string, descricao: string, grupo: ?string, imagem_url: ?string, url_produto: ?string}>, 1: ?int}
     */
    private function fetchListings(): array
    {
        $listings = [];
        $total = null;

        for ($page = 1; $page <= static::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(20)
                ->retry(5, 2000)
                ->withUserAgent(self::USER_AGENT)
                ->get($this->baseUrl.'/produtos/listagem', ['page' => $page])
                ->throw();

            ['listings' => $pageListings, 'total' => $pageTotal] = RoltensProductParser::extractListing($response->body());

            unset($response);
            gc_collect_cycles();

            if ($total === null) {
                $total = $pageTotal;
            }

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
     * @return array{conversoes: ?array<int, string>, aplicacao: ?string}
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

        return RoltensProductParser::extractDetail($body);
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
