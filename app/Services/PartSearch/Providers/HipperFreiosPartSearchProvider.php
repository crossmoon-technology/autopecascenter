<?php

namespace App\Services\PartSearch\Providers;

use App\Services\PartSearch\PartSearchProvider;
use App\Services\PartSearch\PartSearchResult;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class HipperFreiosPartSearchProvider implements PartSearchProvider
{
    private const string BASE_URL = 'https://www.hipperfreios.com.br';

    private const int MAX_RESULTS = 100;

    public function search(string $query): Collection
    {
        $response = Http::timeout(10)
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->get(self::BASE_URL.'/pt-br/produtos/busca', ['cod' => $query])
            ->throw();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->body());
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), "tabela-busca")]//tr[contains(concat(" ", normalize-space(@class), " "), " hover-table ")]');

        $results = collect();

        foreach ($rows as $row) {
            if ($results->count() >= self::MAX_RESULTS) {
                break;
            }

            $cells = $xpath->query('.//td', $row);

            if ($cells->length < 6) {
                continue;
            }

            $codigo = trim($cells->item($cells->length - 1)->textContent);

            if ($codigo === '') {
                continue;
            }

            $image = $xpath->query('.//img', $cells->item(0))->item(0);
            $imagem_src = $image?->getAttribute('src');
            $modelo = trim($cells->item(2)->textContent).' '.trim($cells->item(3)->textContent);
            $ano = trim($cells->item(4)->textContent);
            $data_href = $row->getAttribute('data-href');

            $results->push(new PartSearchResult(
                codigo: $codigo,
                descricao: trim($cells->item(5)->textContent),
                imagem_url: $imagem_src ? self::BASE_URL.'/'.ltrim($imagem_src, './') : null,
                montadora: trim($cells->item(1)->textContent) ?: null,
                modelo: $ano !== '' ? trim("{$modelo} ({$ano})") : ($modelo ?: null),
                product_url: $data_href ? self::BASE_URL.$data_href : null,
            ));
        }

        return $results;
    }
}
