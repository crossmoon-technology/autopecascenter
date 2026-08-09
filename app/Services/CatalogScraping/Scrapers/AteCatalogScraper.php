<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\MsMotorservice\MsMotorserviceProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes ATE's entire catalog (catalogoexpresso.com.br/ATE) — the same
 * "cw"/Ideia2001 platform as MS Motorservice and Mide Parts (identical
 * `__CW_DATA_LISTA_RESULTADO__`/`__CW_DATA_DETALHES_PRODUTO__` script markers
 * and JSON shape, confirmed live), just under a `/ATE` path prefix on the
 * same shared catalogoexpresso.com.br host Hella and Mide Parts already run
 * on (not MS Motorservice's own dedicated subdomain, which is the one that's
 * had repeated connection-drop/timeout failures) — reuses
 * MsMotorserviceProductParser directly rather than duplicating an identical
 * parser under an ATE-specific name.
 *
 * Unlike Mide Parts, this one keeps the `.php` endpoints (`/resultado.php`,
 * `/detalhes.php`) — confirmed live, `cw_ie_tp=0` with no term returns the
 * whole catalog, 4 parts/page (not the 10/page seen elsewhere on this same
 * platform — MsMotorserviceProductParser's listing extraction doesn't assume
 * a fixed page size, so this doesn't need any special handling) — confirmed
 * live: 1097 parts across ~275 pages.
 *
 * Given the shared host's track record so far (Hella and Mide Parts have had
 * no incidents) and this being a similarly small catalog, this skips
 * checkpoint/resume machinery just like Mide Parts does — that's only earned
 * its place on MS Motorservice after real, repeated failures specific to its
 * own dedicated host.
 *
 * It does NOT skip the small-shortfall tolerance, though: a real run came up
 * 4 short of the site's own reported 1097 (confirmed reaching a genuine empty
 * page, not just hitting MAX_PAGES) — the exact same "site's own counter is
 * off by a small, consistent margin" issue MS Motorservice's own tolerance
 * exists for, just observed here directly instead of assumed from that
 * precedent.
 *
 * Product images are hosted on the same c123.com.br "CatalogoExpresso"
 * platform other manufacturers use, under this manufacturer's own numeric
 * folder (confirmed live: /CatalogoExpresso/106/) — and, like Mide Parts but
 * unlike MS Motorservice/Hella, WITHOUT a `/dcp/` subfolder segment: the
 * site's own detail page `<img>` links straight to `/FotoProdWeb/{arquivo}`
 * (confirmed live, a real ~12KB image), not `/FotoProdWeb/dcp/{arquivo}`
 * (also resolves live, but to a different, smaller ~3KB file) — matched to
 * what the site itself actually serves rather than assumed from convention.
 */
class AteCatalogScraper implements CatalogScraper
{
    private const string IMAGE_BASE_URL = 'https://www.c123.com.br/CatalogoExpresso/106/FotoProdWeb/';

    protected const int MAX_PAGES = 500;

    private const int TOTAL_SHORTFALL_TOLERANCE = 10;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total, $reachedEmptyPage] = $this->fetchListings();

        if ($total === null) {
            throw new RuntimeException(sprintf(
                'AteCatalogScraper (%s): não foi possível determinar o total esperado de peças — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($listings);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'AteCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
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
     * @return array{0: array<int, array{codigo_produto: int, codigo: string}>, 1: ?int, 2: bool}
     */
    private function fetchListings(): array
    {
        $listings = [];
        $total = null;
        $reachedEmptyPage = false;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
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

            $pageListings = MsMotorserviceProductParser::extractListing($body);

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
     * @return array{codigo: string, descricao: string, conversoes: ?array<int, string>, fabricante: ?string, grupo: ?string, subgrupo: ?string, aplicacao: ?string, imagem_url: ?string}|null
     */
    private function fetchDetail(int $codigoProduto): ?array
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get($this->baseUrl.'/detalhes.php', [
                'cw_ie_tp' => 0,
                'cw_produtoAtivo' => "CodigoProduto<!2!>{$codigoProduto}",
            ])
            ->throw();

        $body = $response->body();
        unset($response);
        gc_collect_cycles();

        $product = MsMotorserviceProductParser::extractDetail($body);

        if ($product === null) {
            return null;
        }

        return [
            'codigo' => $product['codigo'],
            'descricao' => $product['descricao'],
            'conversoes' => $product['conversoes'],
            'fabricante' => $product['fabricante'],
            'grupo' => $product['grupo'],
            'subgrupo' => $product['subgrupo'],
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
