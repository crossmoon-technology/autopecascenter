<?php

namespace App\Services\PartSearch\Providers;

use App\Services\PartSearch\PartSearchProvider;
use App\Services\PartSearch\PartSearchResult;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Shared search page for Cofap and Magneti Marelli (both brands live on the same
 * WordPress site, mmcofap.com.br, and return the same result markup).
 */
class CofapPartSearchProvider implements PartSearchProvider
{
    private const int MAX_RESULTS = 100;

    public function search(string $query): Collection
    {
        $response = Http::timeout(10)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get('https://mmcofap.com.br/busca-catalogo', ['busca' => $query])
            ->throw();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->body());
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $items = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " specs-busca ")]');

        $results = collect();

        foreach ($items as $item) {
            if ($results->count() >= self::MAX_RESULTS) {
                break;
            }

            $titleDiv = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " item-title ")]', $item)->item(0);

            if (! $titleDiv) {
                continue;
            }

            $link = $xpath->query('.//a[1]', $titleDiv)->item(0);

            if (! $link) {
                continue;
            }

            $href = $link->getAttribute('href');
            parse_str((string) parse_url($href, PHP_URL_QUERY), $query_params);
            $codigo = $query_params['busca'] ?? null;

            if (! $codigo) {
                continue;
            }

            $image = $xpath->query('.//img[contains(concat(" ", normalize-space(@class), " "), " trigger-lightbox ")]', $item)->item(0);
            $italic = $xpath->query('.//i[1]', $titleDiv)->item(0);
            $modelo = $italic?->nextSibling ? trim($italic->nextSibling->textContent) : null;

            $results->push(new PartSearchResult(
                codigo: $codigo,
                descricao: trim($link->textContent),
                imagem_url: $image?->getAttribute('src') ?: null,
                montadora: $italic ? trim(rtrim(trim($italic->textContent), '- ')) : null,
                modelo: $modelo ?: null,
                product_url: $href ?: null,
            ));
        }

        if ($results->isEmpty()) {
            $results = $this->parseExactCodeResult($xpath);
        }

        return $results;
    }

    /**
     * Searching by the exact product code (instead of a free-text term) renders a
     * different page: a single product-info block plus one "Aplicações" block per
     * vehicle fitment, instead of a flat list of matches.
     *
     * @return Collection<int, PartSearchResult>
     */
    private function parseExactCodeResult(DOMXPath $xpath): Collection
    {
        $productBlock = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " busca-codigo-result ")]//div[@class="specs"]')->item(0);

        if (! $productBlock) {
            return collect();
        }

        $product = $this->extractItemMap($xpath, $productBlock);
        $codigo = $product['Código'] ?? null;

        if (! $codigo) {
            return collect();
        }

        $image = $xpath->query('.//img[contains(concat(" ", normalize-space(@class), " "), " trigger-lightbox ")]', $productBlock)->item(0);
        $descricao = $product['Descrição do Grupo'] ?? $product['Tipo'] ?? $codigo;
        $product_url = 'https://mmcofap.com.br/busca-catalogo/?busca='.urlencode($codigo);

        $applications = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " two-column ") and not(contains(concat(" ", normalize-space(@class), " "), " compre-agora "))]');
        $results = collect();

        foreach ($applications as $application) {
            if ($results->count() >= self::MAX_RESULTS) {
                break;
            }

            $fields = $this->extractItemMap($xpath, $application);
            $modelo = trim(implode(' ', array_filter([
                $fields['Veículo'] ?? null,
                $fields['Modelo'] ?? null,
                isset($fields['Ano']) ? "({$fields['Ano']})" : null,
            ])));

            $results->push(new PartSearchResult(
                codigo: $codigo,
                descricao: $descricao,
                imagem_url: $image?->getAttribute('src') ?: null,
                montadora: $fields['Montadora'] ?? null,
                modelo: $modelo ?: null,
                product_url: $product_url,
            ));
        }

        if ($results->isEmpty()) {
            $results->push(new PartSearchResult(
                codigo: $codigo,
                descricao: $descricao,
                imagem_url: $image?->getAttribute('src') ?: null,
                product_url: $product_url,
            ));
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function extractItemMap(DOMXPath $xpath, \DOMNode $context): array
    {
        $map = [];

        foreach ($xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " item ")]', $context) as $item) {
            $title = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " item-title ")]', $item)->item(0);
            $value = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " item-value ")]', $item)->item(0);

            if ($title && $value) {
                $map[trim($title->textContent)] = trim($value->textContent);
            }
        }

        return $map;
    }
}
