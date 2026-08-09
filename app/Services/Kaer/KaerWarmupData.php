<?php

namespace App\Services\Kaer;

/**
 * Kaer's storefront (kaerbrasil.com) runs on Wix. Every page — the search
 * results list and each product's own detail page — embeds the exact data it
 * renders as JSON inside a <script id="wix-warmup-data"> tag, so the bulk
 * catalog scraper (Services\CatalogScraping\Scrapers\KaerCatalogScraper) reads
 * it from there instead of scraping rendered markup.
 */
final class KaerWarmupData
{
    /**
     * Reads /search's result list. Wix mixes non-product hits (e.g. site pages)
     * into the same array when documentType is "all" — filtered out here since
     * neither consumer wants to treat a blog/page hit as a part.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function extractSearchResults(string $html): array
    {
        $data = self::decode($html);

        foreach ($data['appsWarmupData'] ?? [] as $appData) {
            if (isset($appData['search:SearchResponse']['documents'])) {
                return array_values(array_filter(
                    $appData['search:SearchResponse']['documents'],
                    fn (array $document) => ($document['documentType'] ?? null) === 'public/stores/products'
                ));
            }
        }

        return [];
    }

    /**
     * Reads a product detail page (/product-page/{slug}). The warmup key is
     * suffixed with the product's own slug (e.g. "productPage_BRL_257210-606-..."),
     * so it's located by prefix instead of an exact key.
     *
     * @return array<string, mixed>|null the raw catalog.product payload
     */
    public static function extractProductPage(string $html): ?array
    {
        $data = self::decode($html);

        foreach ($data['appsWarmupData'] ?? [] as $appData) {
            foreach ($appData as $key => $value) {
                if (str_starts_with((string) $key, 'productPage_') && isset($value['catalog']['product'])) {
                    return $value['catalog']['product'];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $html): array
    {
        if (! preg_match('/<script type="application\/json" id="wix-warmup-data">(.*?)<\/script>/s', $html, $matches)) {
            return [];
        }

        return json_decode($matches[1], true) ?? [];
    }
}
