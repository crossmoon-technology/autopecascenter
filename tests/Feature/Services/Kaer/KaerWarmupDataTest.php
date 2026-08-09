<?php

namespace Tests\Feature\Services\Kaer;

use App\Services\Kaer\KaerWarmupData;
use Tests\TestCase;

class KaerWarmupDataTest extends TestCase
{
    public function test_extracts_search_results_filtering_out_non_product_documents(): void
    {
        $warmupData = json_encode([
            'appsWarmupData' => [
                '1484cb44-49cd-5b39-9681-75188ab429de' => [
                    'search:SearchResponse' => [
                        'documents' => [
                            ['documentType' => 'public/stores/products', 'title' => 'A1 - 100 - Peça'],
                            ['documentType' => 'public/site/pages', 'title' => 'Página institucional'],
                        ],
                    ],
                ],
            ],
        ]);

        $html = "<script type=\"application/json\" id=\"wix-warmup-data\">{$warmupData}</script>";

        $results = KaerWarmupData::extractSearchResults($html);

        $this->assertCount(1, $results);
        $this->assertSame('A1 - 100 - Peça', $results[0]['title']);
    }

    public function test_returns_an_empty_array_when_the_script_tag_is_missing(): void
    {
        $this->assertSame([], KaerWarmupData::extractSearchResults('<html></html>'));
    }

    public function test_extracts_the_product_page_payload_by_key_prefix(): void
    {
        $warmupData = json_encode([
            'appsWarmupData' => [
                '1380b703-ce81-ff05-f115-39571d94dfcd' => [
                    'productPage_BRL_257210-606-espacador' => [
                        'catalog' => [
                            'product' => ['name' => '257210 - 606 - ESPAÇADOR', 'description' => '<p>texto</p>'],
                        ],
                    ],
                ],
            ],
        ]);

        $html = "<script type=\"application/json\" id=\"wix-warmup-data\">{$warmupData}</script>";

        $product = KaerWarmupData::extractProductPage($html);

        $this->assertSame('257210 - 606 - ESPAÇADOR', $product['name']);
    }

    public function test_returns_null_when_there_is_no_product_page_payload(): void
    {
        $warmupData = json_encode(['appsWarmupData' => []]);
        $html = "<script type=\"application/json\" id=\"wix-warmup-data\">{$warmupData}</script>";

        $this->assertNull(KaerWarmupData::extractProductPage($html));
    }
}
