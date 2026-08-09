<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\MsMotorservice\MsMotorserviceProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes Mide Parts' entire catalog (catalogoexpresso.com.br/mideparts)
 * — the same "cw"/Ideia2001 platform as MS Motorservice (identical
 * `__CW_DATA_LISTA_RESULTADO__`/`__CW_DATA_DETALHES_PRODUTO__` script markers
 * and JSON shape, confirmed live), just under a `/mideparts` path prefix
 * instead of MS Motorservice's own subdomain, and with `.php`-less endpoints
 * (`/resultado`, `/detalhes` vs `/resultado.php`, `/detalhes.php`) — reuses
 * MsMotorserviceProductParser directly rather than duplicating an identical
 * parser under a Mideparts-specific name.
 *
 * `cw_ie_tp=0` with no search term returns the whole catalog, paginated at
 * 10/page via `cw_pgAtual` — confirmed live: 1575 parts. The
 * "cw-total-resultado(-plural)" counter is read the same way as
 * MsMotorserviceProductParser::extractTotal() already does.
 *
 * Pre-emptively uses MS Motorservice's same generous timeout/retry
 * (timeout(20), retry(5, 2000)) since this is the same backend infrastructure
 * that caused MS Motorservice's real repeated connection-drop failures — but,
 * unlike MsMotorserviceCatalogScraper, skips its checkpoint/resume and
 * total-shortfall-tolerance machinery: at 1575 parts/~158 listing pages this
 * is much closer in scale to Hella's (849 parts, no incidents so far) than to
 * MS Motorservice's 4342-part/434-page runs that actually needed it. If a
 * real run here hits the same failures, that's the time to add it — same way
 * MS Motorservice only earned that machinery after real failures of its own.
 *
 * Product images are hosted on the same c123.com.br "CatalogoExpresso"
 * platform other manufacturers use, under this manufacturer's own numeric
 * folder (confirmed live: /CatalogoExpresso/590/) — but, unlike Hella/MS
 * Motorservice, WITHOUT a `/dcp/` subfolder segment: the site's own detail
 * page links straight to `/FotoProdWeb/{arquivo}` (confirmed live, a real
 * ~18KB image), not `/FotoProdWeb/dcp/{arquivo}` (also resolves live, but to
 * a different, smaller ~2.6KB file) — matched to what the site itself
 * actually serves rather than assumed from the other manufacturers'
 * convention.
 */
class MidePartsCatalogScraper implements CatalogScraper
{
    private const string IMAGE_BASE_URL = 'https://www.c123.com.br/CatalogoExpresso/590/FotoProdWeb/';

    private const int MAX_PAGES = 500;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total] = $this->fetchListings();

        if ($total !== null && count($listings) < $total) {
            throw new RuntimeException(sprintf(
                'MidePartsCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
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
                ->get($this->baseUrl.'/resultado', ['cw_ie_tp' => 0, 'cw_pgAtual' => $page])
                ->throw();

            $body = $response->body();
            unset($response);
            gc_collect_cycles();

            if ($total === null) {
                $total = MsMotorserviceProductParser::extractTotal($body);
            }

            $pageListings = MsMotorserviceProductParser::extractListing($body);

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
     * @return array{codigo: string, descricao: string, conversoes: ?array<int, string>, fabricante: ?string, grupo: ?string, subgrupo: ?string, aplicacao: ?string, imagem_url: ?string}|null
     */
    private function fetchDetail(int $codigoProduto): ?array
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get($this->baseUrl.'/detalhes', [
                'retornaJSON' => 'true',
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
