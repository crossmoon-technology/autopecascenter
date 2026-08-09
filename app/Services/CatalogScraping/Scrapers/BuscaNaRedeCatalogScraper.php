<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\BuscaNaRede\BuscaNaRedeProductParser;
use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes a "Busca na Rede" tenant catalog (buscanarede.com.br) —
 * confirmed live: a shared, multi-tenant platform, so `$baseUrl` (e.g.
 * `https://buscanarede.com.br/linmaxbrasil` for LINMAX) is all that varies
 * per manufacturer, same reuse pattern as C123CatalogScraper elsewhere in
 * this app.
 *
 * Listing pages (`/produtos`, then `/produtos/{page}` for page 2+ — a path
 * segment, not a query param) already carry everything except
 * cross-reference codes (see BuscaNaRedeProductParser), confirmed live: a
 * genuinely empty page (0 product cards) once past the real last page —
 * no clamping-to-last-page surprise like Roltens elsewhere in this app.
 * Cross-reference codes need two extra requests per product (the
 * `/equivalences` and `/oem` AJAX tabs on that product's own detail page),
 * so — same two-stage shape as ATE/MG Peças Automotivas — this fingerprints
 * on the cheap listing pass alone before paying for those.
 */
class BuscaNaRedeCatalogScraper implements CatalogScraper
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
                'BuscaNaRedeCatalogScraper (%s): não foi possível determinar o total esperado de produtos — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($listings);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'BuscaNaRedeCatalogScraper (%s): coletou apenas %d de %d produtos esperados — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
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
            $products[] = [
                'codigo' => $listing['codigo'],
                'descricao' => $listing['descricao'],
                'conversoes' => filled($listing['url_produto']) ? $this->fetchConversoes($listing['url_produto']) : null,
                'aplicacao' => $listing['aplicacao'],
                'imagem_url' => $listing['imagem_url'],
            ];

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo: string, descricao: string, imagem_url: ?string, url_produto: ?string, aplicacao: ?string}>, 1: ?int, 2: bool}
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

            $url = $page === 1 ? $this->baseUrl.'/produtos' : $this->baseUrl.'/produtos/'.$page;

            $response = Http::timeout(20)
                ->retry(5, 2000)
                ->withUserAgent(self::USER_AGENT)
                ->get($url)
                ->throw();

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            if ($total === null) {
                $total = BuscaNaRedeProductParser::extractTotal($body);
            }

            $pageListings = BuscaNaRedeProductParser::extractListing($body);

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
     * @return array<int, string>|null
     */
    private function fetchConversoes(string $detailUrl): ?array
    {
        $equivalences = $this->fetchTab($detailUrl.'/equivalences');
        $oem = $this->fetchTab($detailUrl.'/oem');

        return BuscaNaRedeProductParser::extractConversoes($equivalences, $oem);
    }

    private function fetchTab(string $url): string
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent(self::USER_AGENT)
            ->get($url)
            ->throw();

        $body = $response->body();
        unset($response);
        gc_collect_cycles();

        return $body;
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
