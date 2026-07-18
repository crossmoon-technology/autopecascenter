<?php

namespace App\Services\PartSearch;

use Livewire\Wireable;

final readonly class PartSearchResult implements Wireable
{
    public function __construct(
        public string $codigo,
        public string $descricao,
        public ?string $imagem_url = null,
        public ?string $montadora = null,
        public ?string $modelo = null,
        public ?string $product_url = null,
    ) {}

    public function toLivewire(): array
    {
        return [
            'codigo' => $this->codigo,
            'descricao' => $this->descricao,
            'imagem_url' => $this->imagem_url,
            'montadora' => $this->montadora,
            'modelo' => $this->modelo,
            'product_url' => $this->product_url,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new self(
            codigo: $value['codigo'],
            descricao: $value['descricao'],
            imagem_url: $value['imagem_url'],
            montadora: $value['montadora'],
            modelo: $value['modelo'],
            product_url: $value['product_url'],
        );
    }
}
