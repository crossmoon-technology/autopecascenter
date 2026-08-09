<?php

namespace App\Services\CatalogScraping\Scrapers;

use App\Services\CatalogScraping\CatalogScraper;
use App\Services\CatalogScraping\ScrapedCatalog;
use App\Services\NatIndustria\NatIndustriaProductParser;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bulk-scrapes NAT Indústria's catalog (natindustria.com.br) — a WordPress
 * site whose custom "produto" post type isn't exposed via the REST API
 * (confirmed live: /wp-json/wp/v2/types lists only core post types), but is
 * fully enumerable through WordPress's own native XML sitemap instead
 * (/wp-sitemap-posts-produto-1.xml, confirmed live: 1036 URLs on one page).
 *
 * Unlike every other source in this app, that sitemap ALSO gives a cheap
 * "did anything change" signal for free — each `<url>` carries its own
 * `<lastmod>` — so this fingerprints the sitemap's own (url, lastmod) pairs
 * BEFORE ever fetching a single detail page, rather than needing a
 * separate cheap listing pass like ATE/MS Motorservice/MG Peças
 * Automotivas do.
 *
 * See NatIndustriaProductParser for why a single detail page can yield
 * MULTIPLE distinct Part rows (one per color variant).
 */
class NatIndustriaCatalogScraper implements CatalogScraper
{
    private const string USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    private const string SITEMAP_PATH = '/wp-sitemap-posts-produto-1.xml';

    private const int DETAIL_REQUEST_DELAY_MICROSECONDS = 150_000;

    public function __construct(private readonly string $baseUrl) {}

    public function scrape(?string $known_source_version): ?ScrapedCatalog
    {
        $response = Http::timeout(20)
            ->retry(5, 2000)
            ->withUserAgent(self::USER_AGENT)
            ->get($this->baseUrl.self::SITEMAP_PATH)
            ->throw();

        $entries = NatIndustriaProductParser::extractSitemap($response->body());
        unset($response);
        gc_collect_cycles();

        if ($entries === []) {
            throw new RuntimeException(sprintf(
                'NatIndustriaCatalogScraper (%s): sitemap de produtos vazio ou não encontrado — abortando sem importar para não sobrescrever o catálogo com dados possivelmente incompletos.',
                $this->baseUrl
            ));
        }

        $source_version = $this->fingerprint($entries);

        if ($source_version === $known_source_version) {
            return null;
        }

        $products = [];

        foreach ($entries as $index => $entry) {
            if ($index > 0) {
                usleep(self::DETAIL_REQUEST_DELAY_MICROSECONDS);
            }

            $products = [...$products, ...$this->fetchDetail($entry['url'])];
        }

        return new ScrapedCatalog(source_version: $source_version, products: $products);
    }

    /**
     * @return array<int, array{codigo: string, descricao: string, aplicacao: ?string, imagem_url: ?string}>
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

        return NatIndustriaProductParser::extractDetail($body);
    }

    /**
     * @param  array<int, array{url: string, lastmod: ?string}>  $entries
     */
    private function fingerprint(array $entries): string
    {
        $signature = collect($entries)
            ->map(fn (array $entry) => $entry['url'].'|'.($entry['lastmod'] ?? ''))
            ->sort()
            ->implode("\n");

        return hash('sha256', $signature);
    }
}
