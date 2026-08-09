<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\MsMotorservice\MsMotorserviceProductParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Bulk-scrapes MS Motorservice's entire catalog (catweb.ms-motorservice.com.br
 * — Kolbenschmidt/KS, BF, and other sub-brands sold under the same storefront).
 * Submitting the search with cw_ie_tp=0 and no term returns the WHOLE catalog,
 * paginated at 10 parts/page via `cw_pgAtual` — confirmed live: 4342 parts
 * across 434 pages, with a "N produtos" counter used the same way C123's
 * `mTotPrd`/RPD's "Total listado" are: to hard-fail on a short listing rather
 * than silently import a partial catalog.
 *
 * Cross-reference/OEM codes (needed for conversões) only live on each
 * product's own detail page, not the listing — same two-stage shape as Kaer
 * (cheap listing pass first to enumerate + fingerprint, expensive per-product
 * detail fetch only if the fingerprint actually changed), but at ~33x Kaer's
 * scale: 4342 detail requests. Confirmed live timing (~1.4s/listing page,
 * ~0.9s/detail page under normal load) puts a full run at roughly 90 minutes
 * — ScrapeCatalog's job timeout has to comfortably clear that.
 *
 * resultado.php's own response time (the LISTING endpoint, `detalhes.php`
 * does NOT show this) grows with `cw_pgAtual` itself — confirmed live,
 * measured directly against the site: ~1s at page 1, ~2.5s at page 50, ~4s
 * at page 100, ~6.7s at page 200, ~12.7s at page 300 — a roughly linear
 * climb that, by the 390-434 range, lands right at (or past) whatever
 * timeout is set, regardless of retrying (the SAME request is slow every
 * time, not a one-off blip). Explains five real failed runs at pages deep
 * in the pagination (224, 139, then 433/434 — basically done — then, after
 * bumping timeout(10)→timeout(20), 398 and 417): each one hit this same
 * page-position-correlated slowness, not random throttling as originally
 * suspected. timeout(20) still wasn't quite enough headroom, hence
 * timeout(45) — retry(5, 2000) stays in place too, since the growth isn't
 * perfectly deterministic (139 and 224 failing much earlier than the 390s
 * shows real run-to-run variance on top of the general upward trend).
 * detalhes.php's own timeout(20) is untouched — no pagination depth for it
 * to scale against, each request is an O(1) lookup by product id.
 *
 * Product images are hosted on the same c123.com.br "CatalogoExpresso"
 * platform C123CatalogScraper already targets for other manufacturers, under
 * this manufacturer's own numeric folder (confirmed live: /CatalogoExpresso/479/).
 *
 * The site's own "N produtos" counter turned out to be unreliable by a small,
 * consistent margin: confirmed live (re-checked fresh, not a one-off) that it
 * announces 4342 while pagination genuinely and repeatedly ends at an empty
 * page after only 4332 — a real source-side inconsistency, not a scrape
 * failure. So completeness is judged primarily by actually reaching an empty
 * page (the real "done" signal — a fetch failure raises its own exception
 * instead of silently yielding one), and the total-vs-collected comparison
 * only hard-fails when either no empty page was ever reached (pagination hit
 * MAX_PAGES instead — a different, more suspicious way to stop) or the gap is
 * bigger than TOTAL_SHORTFALL_TOLERANCE (a real truncation, not the site's
 * own small counter drift).
 *
 * Three real runs died to that same silent-connection-drop failure well into
 * the pagination (retry(5, 2000) exhausted each time) — first at page 224,
 * then 139, then 433 of 434 (basically done). Since each failed run restarted
 * the ENTIRE ~70+ minute listing pass from page 1, losing everything already
 * fetched, both stages (listing and per-product detail) checkpoint their
 * progress to disk after every successful page/product — a retry resumes
 * from the last saved point instead of from scratch. Checkpoints are cleared
 * on a successful scrape (or a confirmed-unchanged skip); a checkpoint from a
 * stale listing (fingerprint mismatch) is discarded rather than trusted.
 */
class MsMotorserviceCatalogScraper implements CatalogScraper
{
    private const string IMAGE_BASE_URL = 'https://www.c123.com.br/CatalogoExpresso/479/FotoProdWeb/dcp/';

    protected const int MAX_PAGES = 1000;

    private const int TOTAL_SHORTFALL_TOLERANCE = 20;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        [$listings, $total, $reachedEmptyPage] = $this->fetchListings();

        // Um total nunca resolvido não pode virar "0 de confiança" (ao contrário do
        // C123CatalogScraper, onde isso é aceitável pro marcador de última
        // atualização) — já aconteceu ao vivo de uma página vir sem o marcador de
        // total de forma transitória e o restante da paginação parar cedo (~101 de
        // ~4332 peças reais) sem nunca disparar essa proteção, porque sem total
        // conhecido o "shortfall" sempre dava 0. Sem total, não há como validar
        // completude nenhuma — é falha dura sempre, nunca "segue assim mesmo".
        if ($total === null) {
            throw new RuntimeException(sprintf(
                'MsMotorserviceCatalogScraper (%s): não foi possível determinar o total esperado de peças — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $shortfall = $total - count($listings);
        $shortfallTooBigToTrust = ! $reachedEmptyPage || $shortfall > self::TOTAL_SHORTFALL_TOLERANCE;

        if ($shortfall > 0 && $shortfallTooBigToTrust) {
            throw new RuntimeException(sprintf(
                'MsMotorserviceCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($listings),
                $total
            ));
        }

        $source_version = $this->fingerprint($listings);

        if ($source_version === $known_source_version) {
            $this->clearCheckpoint('listing');
            $this->clearCheckpoint('detail');

            return null;
        }

        $products = $this->fetchDetails($listings, $source_version);

        $this->clearCheckpoint('listing');
        $this->clearCheckpoint('detail');

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array{0: array<int, array{codigo_produto: int, codigo: string}>, 1: ?int, 2: bool}
     */
    private function fetchListings(): array
    {
        $checkpoint = $this->loadCheckpoint('listing');
        $listings = $checkpoint['listings'] ?? [];
        $total = $checkpoint['total'] ?? null;
        $startPage = $checkpoint['next_page'] ?? 1;
        $reachedEmptyPage = false;

        for ($page = $startPage; static::underTotal(count($listings), $total) && $page <= static::MAX_PAGES; $page++) {
            if ($page > 1) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(45)
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

            $this->saveCheckpoint('listing', ['listings' => $listings, 'total' => $total, 'next_page' => $page + 1]);

            if ($total !== null && count($listings) >= $total) {
                break;
            }
        }

        return [$listings, $total, $reachedEmptyPage];
    }

    private static function underTotal(int $collected, ?int $total): bool
    {
        return $total === null || $collected < $total;
    }

    /**
     * @param  array<int, array{codigo_produto: int, codigo: string}>  $listings
     * @return array<int, array{codigo: string, descricao: string, conversoes: ?array<int, string>, fabricante: ?string, grupo: ?string, subgrupo: ?string, aplicacao: ?string, imagem_url: ?string}>
     */
    private function fetchDetails(array $listings, string $source_version): array
    {
        $checkpoint = $this->loadCheckpoint('detail');
        // Um checkpoint de detalhe só é confiável se veio da MESMA listagem (fingerprint
        // batendo) — se o catálogo mudou entre uma tentativa falha e essa, o progresso
        // salvo pode não corresponder mais aos produtos atuais, então é descartado.
        $trustCheckpoint = $checkpoint !== null && ($checkpoint['fingerprint'] ?? null) === $source_version;

        $products = $trustCheckpoint ? $checkpoint['products'] : [];
        $done = $trustCheckpoint ? array_flip($checkpoint['done']) : [];

        foreach ($listings as $listing) {
            if (isset($done[$listing['codigo_produto']])) {
                continue;
            }

            $product = $this->fetchDetail($listing['codigo_produto']);

            if ($product !== null) {
                $products[] = $product;
            }

            $done[$listing['codigo_produto']] = true;

            $this->saveCheckpoint('detail', [
                'products' => $products,
                'done' => array_keys($done),
                'fingerprint' => $source_version,
            ]);

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return $products;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadCheckpoint(string $stage): ?array
    {
        $disk = Storage::disk('local');
        $path = $this->checkpointPath($stage);

        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode($disk->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveCheckpoint(string $stage, array $data): void
    {
        Storage::disk('local')->put($this->checkpointPath($stage), json_encode($data));
    }

    private function clearCheckpoint(string $stage): void
    {
        Storage::disk('local')->delete($this->checkpointPath($stage));
    }

    private function checkpointPath(string $stage): string
    {
        return 'catalogs/checkpoints/ms-motorservice-'.md5($this->baseUrl)."-{$stage}.json";
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
                'cw_pgAtual' => 1,
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
