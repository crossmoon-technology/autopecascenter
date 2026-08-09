<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\Kaer\KaerTitleParser;
use App\Services\Kaer\KaerWarmupData;
use Illuminate\Support\Facades\Http;

/**
 * Bulk-scrapes Kaer's entire catalog (kaerbrasil.com, Wix). Unlike the C123
 * engine, Kaer's catalog is small (~130 parts) and each product's full detail
 * (multiple images, formatted description) only lives on its own detail page
 * — the search results list only carries a single thumbnail and a plain-text
 * description — so, given the small size, this scraper pages through
 * /search?q=&page=N to build the list of parts, then fetches each part's own
 * detail page individually. Unlike C123 there's no "last updated" marker on
 * the site, so change-detection uses a content hash of the listing instead.
 *
 * Guzzle's Request/Response/Stream objects hold circular references that PHP's
 * refcounting alone never frees — only the cycle collector does, and it
 * doesn't run often enough on its own across ~145 sequential HTTP calls,
 * confirmed live to leak ~1MB/request and exhaust a 128MB memory_limit around
 * request #110-120. gc_collect_cycles() after every request keeps memory flat.
 */
class KaerCatalogScraper implements CatalogScraper
{
    private const int MAX_PAGES = 50;

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 200_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $listings = $this->fetchListings();
        $source_version = $this->fingerprint($listings);

        if ($source_version === $known_source_version) {
            return null;
        }

        $products = [];

        foreach ($listings as $listing) {
            $product = $this->fetchDetail($listing);

            if ($product !== null) {
                $products[] = $product;
            }

            usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array<int, array{id: string, title: string, relativeUrl: string}>
     */
    private function fetchListings(): array
    {
        $listings = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = Http::timeout(10)
                ->retry(3, 500)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->get($this->baseUrl.'/search', ['q' => '', 'page' => $page])
                ->throw();

            $documents = KaerWarmupData::extractSearchResults($response->body());
            unset($response);
            gc_collect_cycles();

            if ($documents === []) {
                break;
            }

            foreach ($documents as $document) {
                $listings[] = [
                    'id' => (string) ($document['id'] ?? ''),
                    'title' => (string) ($document['title'] ?? ''),
                    'relativeUrl' => (string) ($document['relativeUrl'] ?? ''),
                ];
            }
        }

        return $listings;
    }

    /**
     * @param  array<int, array{id: string, title: string, relativeUrl: string}>  $listings
     */
    private function fingerprint(array $listings): string
    {
        $ids = collect($listings)->pluck('id')->sort()->implode('|');

        return hash('sha256', $ids);
    }

    /**
     * @param  array{id: string, title: string, relativeUrl: string}  $listing
     * @return array{codigo: string, descricao: string, detalhes: ?string, conversoes: ?array<int, string>, imagem_url: ?string}|null
     */
    private function fetchDetail(array $listing): ?array
    {
        if ($listing['relativeUrl'] === '') {
            return null;
        }

        $response = Http::timeout(10)
            ->retry(3, 500)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get($this->baseUrl.$listing['relativeUrl'])
            ->throw();

        $product = KaerWarmupData::extractProductPage($response->body());
        unset($response);
        gc_collect_cycles();

        if ($product === null) {
            return null;
        }

        $parsed = KaerTitleParser::parse((string) ($product['name'] ?? $listing['title']));
        $detalhes = $this->cleanDescription((string) ($product['description'] ?? ''));
        $imagem_url = $product['media'][0]['fullUrl'] ?? null;

        return [
            'codigo' => $parsed['codigo'],
            'descricao' => $parsed['descricao'],
            'detalhes' => $detalhes !== '' ? $detalhes : null,
            'conversoes' => $this->extractConversoes($detalhes, $parsed['codigo']),
            'imagem_url' => $imagem_url,
        ];
    }

    /**
     * A descrição sempre começa com "CÓDIGOS: <código principal> [outros códigos
     * equivalentes] [(MARCA)]APLICAÇÃO: ..." — os códigos além do principal são
     * as conversões/equivalências da peça (ex: "CÓDIGOS: 41210621R - 41033070
     * (IVECO)" → 41033070 é o código IVECO equivalente). Sem isso, essas peças
     * nunca entrariam no pareamento de equivalências (RebuildPartEquivalences).
     *
     * O próprio catálogo da Kaer é inconsistente aqui: às vezes repete o código
     * principal com espaço/caixa diferente em vez de trazer uma conversão nova
     * (ex: "8141585 X" pro código "8141585X"), e às vezes escreve o MESMO código
     * de formas diferentes usando "/" ou espaço como separador de grupos de
     * dígitos (ex: "2S2/711/118", "2S2711118" e "2S2 711 118" são o mesmo
     * código) — isso não é lixo a ser descartado, é a formatação bagunçada da
     * própria fonte, então a comparação/dedupe usa só caracteres alfanuméricos.
     * Como uma barra isolada dentro de dígitos é formatação (não separador),
     * só "//" (duas ou mais seguidas, tipicamente usado entre códigos
     * diferentes) é tratado como separador de token — uma "/" sozinha nunca
     * quebra um token ao meio. Cada token final ainda precisa ter dígito e
     * tamanho mínimo pra não virar uma palavra do texto livre (ex: "KIT",
     * "CAIXA") ou um fragmento sem sentido (ex: o "711" isolado que sobra ao
     * quebrar "2S2 711 118" pelos espaços).
     *
     * @return array<int, string>|null
     */
    private function extractConversoes(string $detalhes, string $codigo): ?array
    {
        if (! preg_match('/C[ÓO]DIGOS:?\s*(.*?)(?:APLICA[ÇC][ÃA]O|DADOS T[ÉE]CNICOS|$)/su', $detalhes, $matches)) {
            return null;
        }

        // Marcas entre parênteses (ex: "(IVECO)") identificam de quem é o código, não
        // são um código em si — descartadas aqui já que o pareamento de equivalências
        // usa só os valores (ver RebuildPartEquivalences::flatten()), não a marca.
        $segment = (string) preg_replace('/\([^)]*\)/', ' ', $matches[1]);
        $normalizedCodigo = $this->stripToAlnum($codigo);

        if ($this->stripToAlnum($segment) === $normalizedCodigo) {
            return null;
        }

        $codigos = collect(preg_split('/\s*\/{2,}\s*|[\s\-|]+/u', $segment, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $token): string => $this->stripToAlnum($token))
            ->filter(fn (string $token): bool => strlen($token) >= 4 && preg_match('/\d/', $token) === 1)
            ->reject(fn (string $token): bool => $token === $normalizedCodigo)
            ->unique()
            ->values();

        return $codigos->isNotEmpty() ? $codigos->all() : null;
    }

    private function stripToAlnum(string $value): string
    {
        return strtoupper((string) preg_replace('/[^a-zA-Z0-9]/u', '', $value));
    }

    private function cleanDescription(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', ' ', $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // html_entity_decode() turns &nbsp; into a real U+00A0, which plain \s
        // (no /u modifier) doesn't recognize as whitespace to collapse below.
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
