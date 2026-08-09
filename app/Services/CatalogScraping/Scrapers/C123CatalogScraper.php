<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\C123\C123ProductParser;
use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes an entire catalog from a c123.com.br-hosted storefront (see
 * FaniaCatalogScraper for a meaningfully different variant of this same
 * engine). res.asp with no search params returns the whole catalog, but
 * paginated: only the first ~18 products are
 * embedded in that page (plus the grand total and a "last updated" marker) —
 * the rest is fetched via resaj.asp?ic={offset}, which requires replaying the
 * session cookie from the first response and manually urldecoding the classic
 * escape()-style encoded payload.
 *
 * Configured per manufacturer via config/scrapers.php (just a different `url`
 * per entry) rather than one subclass per site — every storefront on this
 * particular legacy engine shares this exact page structure, confirmed live
 * for at least Willtec and Bel-Ar. A real Willtec catalog turned out to have
 * 13385 parts (~450 pages) — well past what this scraper was originally
 * tuned for — so pagination is throttled a bit, and if it ever stops short of
 * the total mTotPrd reported by the site (rate limiting, a transient bad page,
 * hitting the page cap), that's treated as a hard failure rather than quietly
 * importing a truncated catalog: an incomplete result would still update
 * source_version, so the next scheduled run would think nothing changed and
 * skip it — silently freezing the catalog at whatever partial state this run
 * produced.
 *
 * Some storefronts on this same engine (confirmed live: Taranto) also embed
 * cross-reference/OEM codes as `mRef[N]=...` blocks alongside every mPrd
 * batch — both in the initial res.asp body and in every subsequent resaj.asp
 * page — keyed by codigoInterno (see C123ProductParser). This scraper always
 * collects them (harmless no-op for storefronts without any, like Willtec/
 * Bel-Ar/Cipec — extractCrossReferences() just returns an empty array) rather
 * than needing a Taranto-specific subclass.
 *
 * Guzzle's Request/Response/Stream objects hold circular references that PHP's
 * refcounting alone never frees — only the cycle collector does, and it
 * doesn't run often enough on its own across hundreds of sequential HTTP
 * calls, confirmed live to leak ~1MB/request and exhaust a 128MB memory_limit
 * well before 450 requests. gc_collect_cycles() after every request keeps
 * memory flat.
 */
class C123CatalogScraper implements CatalogScraper
{
    private const int MAX_PAGES = 2000;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $first = Http::timeout(10)
            ->retry(3, 500)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get($this->baseUrl.'/res.asp')
            ->throw();

        $body = mb_convert_encoding($first->body(), 'UTF-8', 'ISO-8859-1');
        $source_version = $this->extractSourceVersion($body);
        $cookie = $this->extractSessionCookie($first);
        unset($first);
        gc_collect_cycles();

        // Só pula quando o marcador foi lido com sucesso E bate com o conhecido —
        // se não conseguirmos nem parsear o marcador, seguimos com a importação em
        // vez de arriscar nunca mais atualizar por falha silenciosa de parsing.
        if ($source_version !== null && $source_version === $known_source_version) {
            return null;
        }

        $total = $this->extractTotal($body);
        $products = C123ProductParser::extractProducts($body);
        $crossReferences = C123ProductParser::extractCrossReferences($body);

        for ($page = 0; count($products) < $total && $page < self::MAX_PAGES; $page++) {
            usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);

            $response = Http::timeout(10)
                ->retry(3, 500)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeaders($cookie !== null ? ['Cookie' => $cookie] : [])
                ->get($this->baseUrl.'/resaj.asp', [
                    'ic' => count($products),
                    'dt' => now()->timestamp,
                ])
                ->throw();

            $raw = $response->body();
            unset($response);
            gc_collect_cycles();

            if (($raw[0] ?? null) !== '1') {
                break;
            }

            $decoded = mb_convert_encoding(urldecode(substr($raw, 1)), 'UTF-8', 'ISO-8859-1');
            $pageProducts = C123ProductParser::extractProducts($decoded);

            if ($pageProducts === []) {
                break;
            }

            $products = [...$products, ...$pageProducts];
            // NUNCA usar [...$a, ...$b] (spread) aqui: codigoInterno vira chave
            // numérica de verdade (PHP converte string numérica de chave de
            // array pra int automaticamente), e spread — assim como
            // array_merge() — reindexa chaves inteiras sequencialmente,
            // destruindo o mapeamento codigoInterno=>referências. `+` preserva
            // todas as chaves (cada página tem um conjunto disjunto delas).
            $crossReferences += C123ProductParser::extractCrossReferences($decoded);
        }

        if (count($products) < $total) {
            throw new RuntimeException(sprintf(
                'C123CatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($products),
                $total
            ));
        }

        return new ScrapedCatalog(
            source_version: $source_version,
            products: array_map(fn (array $product) => $this->toProductRow($product, $crossReferences), $products),
        );
    }

    /**
     * @param  array{codigoInterno: string, codigo: string, descricao: string, imagem: string, grupo: string, subgrupo: string}  $product
     * @param  array<string, array<int, string>>  $crossReferences
     * @return array{codigo: string, descricao: string, conversoes: ?array<int, string>, grupo: string, subgrupo: string, imagem_url: ?string}
     */
    private function toProductRow(array $product, array $crossReferences): array
    {
        $imagem = trim(explode('|', $product['imagem'])[0]);

        return [
            'codigo' => $product['codigo'],
            'descricao' => $product['descricao'],
            'conversoes' => C123ProductParser::normalizeConversoes($crossReferences[$product['codigoInterno']] ?? [], $product['codigo']),
            'grupo' => $product['grupo'],
            'subgrupo' => $product['subgrupo'],
            'imagem_url' => $imagem !== '' ? $this->baseUrl.'/FotoProd/dcp/'.$imagem : null,
        ];
    }

    private function extractSessionCookie(Response $response): ?string
    {
        $cookies = collect($response->headers())
            ->first(fn (array $values, string $name) => strcasecmp($name, 'Set-Cookie') === 0) ?? [];

        $cookie = collect($cookies)
            ->map(fn (string $header) => trim(explode(';', $header)[0]))
            ->filter()
            ->implode('; ');

        return $cookie !== '' ? $cookie : null;
    }

    private function extractSourceVersion(string $body): ?string
    {
        preg_match('/id=divUltimaAtualizacao[^>]*>(.*?)<\/DIV>/i', $body, $matches);

        return isset($matches[1]) ? trim($matches[1]) : null;
    }

    private function extractTotal(string $body): int
    {
        preg_match('/mTotPrd=(\d+)/', $body, $matches);

        return isset($matches[1]) ? (int) $matches[1] : 0;
    }
}
