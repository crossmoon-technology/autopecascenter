<?php

namespace App\Services\PartSearch;

use Livewire\Wireable;

/**
 * One result from a live (not bulk-imported) manufacturer search — see
 * PartSearchProvider. Ephemeral: never persisted, has no Part id to hang a
 * favorite/quotation off of the normal way (see
 * Filament\Pages\Buscas\CatalogDatabaseSearch's live-results handling).
 */
final readonly class PartSearchResult implements Wireable
{
    /**
     * @param  array<int, string>  $conversoes
     */
    public function __construct(
        public string $codigo,
        public string $descricao,
        public ?string $imagem_url = null,
        public ?string $aplicacao = null,
        public array $conversoes = [],
        public ?string $product_url = null,
    ) {}

    public function toLivewire(): array
    {
        return [
            'codigo' => $this->codigo,
            'descricao' => $this->descricao,
            'imagem_url' => $this->imagem_url,
            'aplicacao' => $this->aplicacao,
            'conversoes' => $this->conversoes,
            'product_url' => $this->product_url,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new self(
            codigo: $value['codigo'],
            descricao: $value['descricao'],
            imagem_url: $value['imagem_url'],
            aplicacao: $value['aplicacao'],
            conversoes: $value['conversoes'],
            product_url: $value['product_url'],
        );
    }
}
