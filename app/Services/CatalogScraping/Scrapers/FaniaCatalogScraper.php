<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\C123\C123ProductParser;
use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes Fania's catalog (c123.com.br/fania) — same legacy ASP engine as
 * Willtec/Bel-Ar (see C123CatalogScraper), but a meaningfully different
 * variant, confirmed live:
 *
 * - res.asp carries NO product data, no `mTotPrd`, and no last-updated marker
 *   at all — everything (including the total) only comes from resaj.asp,
 *   even the very first batch. So, unlike C123CatalogScraper, there's no
 *   cheap first request to read before deciding whether to paginate — every
 *   run starts straight into resaj.asp?ic=0 (still needs the session cookie
 *   from an initial res.asp GET, same as Willtec/Bel-Ar).
 * - No last-updated marker means no cheap "did it change" check either — same
 *   tradeoff as Kaer/RPD/Hella: always traverse fully, then fingerprint
 *   (sha256 of the sorted codigo list) to decide whether to bother
 *   storing/importing.
 * - Every resaj.asp batch also embeds cross-reference/OEM codes as
 *   `mRef[N]=new fR();...`, keyed by the SAME N as the product's own
 *   codigoInterno (`c=`) — confirmed live those line up 1:1, gets conversões
 *   for free without a separate per-product detail fetch (same mechanism
 *   C123CatalogScraper now also uses for storefronts on this engine, like
 *   Taranto, that carry mRef alongside a normal res.asp).
 * - Batches are 60 products each here (Willtec/Bel-Ar use 30).
 */
class FaniaCatalogScraper implements CatalogScraper
{
    private const int MAX_PAGES = 200;

    private const int PAGE_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $first = Http::timeout(10)
            ->retry(3, 500)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get($this->baseUrl.'/res.asp')
            ->throw();

        $cookie = $this->extractSessionCookie($first);
        unset($first);
        gc_collect_cycles();

        $products = [];
        $crossReferences = [];
        $total = null;

        for ($page = 0; self::underTotal(count($products), $total) && $page < self::MAX_PAGES; $page++) {
            if ($page > 0) {
                usleep(self::PAGE_REQUEST_DELAY_MICROSECONDS);
            }

            $response = Http::timeout(10)
                ->retry(3, 500)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeaders($cookie !== null ? ['Cookie' => $cookie] : [])
                ->get($this->baseUrl.'/resaj.asp', ['ic' => count($products), 'dt' => now()->timestamp])
                ->throw();

            $raw = $response->body();
            unset($response);
            gc_collect_cycles();

            if (($raw[0] ?? null) !== '1') {
                break;
            }

            $decoded = mb_convert_encoding(urldecode(substr($raw, 1)), 'UTF-8', 'ISO-8859-1');

            if ($total === null) {
                $total = $this->extractTotal($decoded);
            }

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

        if ($total !== null && count($products) < $total) {
            throw new RuntimeException(sprintf(
                'FaniaCatalogScraper (%s): coletou apenas %d de %d peças esperadas — abortando sem importar para não sobrescrever o catálogo com dados incompletos.',
                $this->baseUrl,
                count($products),
                $total
            ));
        }

        $source_version = $this->fingerprint($products);

        if ($source_version === $known_source_version) {
            return null;
        }

        return new ScrapedCatalog(
            source_version: $source_version,
            products: array_map(fn (array $product) => $this->toProductRow($product, $crossReferences), $products),
        );
    }

    private static function underTotal(int $collected, ?int $total): bool
    {
        return $total === null || $collected < $total;
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

    private function extractTotal(string $body): ?int
    {
        return preg_match('/mTotPrd=(\d+)/', $body, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * @param  array<int, array{codigo: string}>  $products
     */
    private function fingerprint(array $products): string
    {
        $codigos = collect($products)->pluck('codigo')->sort()->implode('|');

        return hash('sha256', $codigos);
    }
}
