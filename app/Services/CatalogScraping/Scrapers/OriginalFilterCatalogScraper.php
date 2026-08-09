<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\MsMotorservice\MsMotorserviceProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes Original Filter's catalog (catalogoexpresso.com.br/original-filter)
 * — the same "cw"/Ideia2001 platform as MS Motorservice/ATE/IKS, confirmed live
 * via the identical `__CW_DATA_LISTA_RESULTADO__`/`cw-total-resultado(-plural)`
 * markers. Combines the two variations already seen separately elsewhere on
 * this platform: it keeps the `.php` endpoint (`/resultado.php`, like ATE, not
 * `/busca` like IKS/Mide Parts), but — like IKS — its LISTING blob already
 * embeds the full per-product shape (DescricaoProduto, FabricantesAplicacao,
 * ReferenciasCruzada, DescricaoGrupoProduto) confirmed live, so no per-product
 * detail fetch is needed at all (`DescricaoSubGrupoProduto` is the only field
 * absent from the listing here; MsMotorserviceProductParser::extractFullListing()
 * already tolerates that being null).
 *
 * Confirmed live: 20 parts/page, `cw-total-resultado-plural` reports 3263, but
 * a genuine empty page is reached at exactly 3243 (162 full pages + a
 * 3-item page 163, then page 164 truly empty — confirmed reproducibly across
 * two independent full-catalog fetches, no duplicate codes collected) — the
 * same "site's own counter is off by a small, consistent margin" issue ATE's
 * tolerance exists for elsewhere on this platform, just a bigger margin here
 * (20 short), hence the wider tolerance below.
 *
 * Product images: confirmed live the page's own displayed `<img>` links to
 * `.../CatalogoExpresso/149/FotoProdWeb/dcp/{arquivo}` on `www.c123.com.br` —
 * WITH the `/dcp/` segment (unlike ATE, which is on the same shared host but
 * without it) — matched to what the site itself actually serves rather than
 * assumed from convention (the no-`/dcp/` path also resolves live, but to a
 * different, higher-resolution file the site doesn't actually use for this
 * manufacturer).
 */
class OriginalFilterCatalogScraper implements CatalogScraper
{
    private const string IMAGE_BASE_URL = 'https://www.c123.com.br/CatalogoExpresso/149/FotoProdWeb/dcp/';

    protected const int MAX_PAGES = 500;

    private const int TOTAL_SHORTFALL_TOLERANCE = 25;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total, $reachedEmptyPage] = $this->fetchListings();

        if ($total === null) {
            throw new RuntimeException(sprintf(
                'OriginalFilterCatalogScraper (%s): não foi possível determinar o total esperado de peças — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($listings);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'OriginalFilterCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
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
                ->get($this->baseUrl.'/resultado.php', ['cw_ie_tp' => 0, 'cw_pgAtual' => $page])
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
