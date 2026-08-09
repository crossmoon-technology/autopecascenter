<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\MsMotorservice\MsMotorserviceProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes IKS's catalog (iks.com.br/busca) — the same "cw"/Ideia2001
 * platform as MS Motorservice, ATE, Mide Parts and Hella, confirmed live via
 * the identical `__CW_DATA_LISTA_RESULTADO__`/`cw-total-resultado(-plural)`
 * markers — but a meaningfully different configuration of it: unlike MS
 * Motorservice/ATE, IKS's LISTING blob already embeds the full per-product
 * shape (DescricaoProduto, FabricantesAplicacao, ReferenciasCruzada) that
 * elsewhere on this platform only shows up on a separate detail-page fetch
 * (see MsMotorserviceProductParser::extractFullListing(), added for this) —
 * confirmed live across multiple pages, not just page 1. Only grupo/subgrupo
 * are absent from IKS's listing; everything else, including cross-reference/
 * OEM codes, comes for free. So this scraper needs no per-product detail
 * fetch at all — just paginate the listing (confirmed live: 2334 products,
 * 10/page) and fingerprint on the resulting codigo list, no cheap
 * "did it change" marker existing here (same tradeoff as Kaer/RPD/Fania).
 *
 * Product images: confirmed live the page's own displayed `<img>` tag
 * (`id="cw-arquivo-foto-produto"`) points at `.../FotoProdWeb/dcp/{arquivo}`
 * on `www.ideia2001.com.br` (NOT `www.c123.com.br`, unlike every other
 * scraper on this platform elsewhere in this app) — matched to what the
 * page itself actually serves rather than assumed from convention, same
 * technique used for MS Motorservice/Hella's own `/dcp/` confirmation.
 */
class IksCatalogScraper implements CatalogScraper
{
    private const string IMAGE_BASE_URL = 'https://www.ideia2001.com.br/CatalogoExpresso/417/FotoProdWeb/dcp/';

    protected const int MAX_PAGES = 500;

    private const int TOTAL_SHORTFALL_TOLERANCE = 20;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total, $reachedEmptyPage] = $this->fetchListings();

        if ($total === null) {
            throw new RuntimeException(sprintf(
                'IksCatalogScraper (%s): não foi possível determinar o total esperado de peças — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($listings);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'IksCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($listings),
                $total
            ));
        }

        $source_version = $this->fingerprint($listings);

        if ($source_version === $known_source_version) {
            return null;
        }

        $products = collect($listings)
            ->map(fn (array $product) => [
                'codigo' => $product['codigo'],
                'descricao' => $product['descricao'],
                'conversoes' => $product['conversoes'],
                'fabricante' => $product['fabricante'],
                'grupo' => $product['grupo'],
                'subgrupo' => $product['subgrupo'],
                'aplicacao' => $product['aplicacao'],
                'imagem_url' => $product['imagem_arquivo'] !== null ? self::IMAGE_BASE_URL.$product['imagem_arquivo'] : null,
            ])
            ->all();

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo: string, descricao: string, fabricante: ?string, grupo: ?string, subgrupo: ?string, aplicacao: ?string, conversoes: ?array<int, string>, imagem_arquivo: ?string}>, 1: ?int, 2: bool}
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
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->get($this->baseUrl.'/busca', ['cw_ie_tp' => 0, 'cw_pgAtual' => $page])
                ->throw();

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            if ($total === null) {
                $total = MsMotorserviceProductParser::extractTotal($body);
            }

            $pageListings = MsMotorserviceProductParser::extractFullListing($body);

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
     * @param  array<int, array{codigo: string}>  $listings
     */
    private function fingerprint(array $listings): string
    {
        $codigos = collect($listings)->pluck('codigo')->sort()->implode('|');

        return hash('sha256', $codigos);
    }
}
