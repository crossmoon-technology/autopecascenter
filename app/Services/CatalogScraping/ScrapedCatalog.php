<?php

namespace App\Services\CatalogScraping;

final readonly class ScrapedCatalog
{
    /**
     * @param  array<int, array{codigo: string, descricao: string, grupo: string, subgrupo: string, imagem_url: ?string}>  $products
     */
    public function __construct(
        public ?string $source_version,
        public array $products,
    ) {}
}
