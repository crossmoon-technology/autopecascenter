<?php

namespace App\Services\PartSearch;

use App\Models\Manufacturer;
use App\Services\PartSearch\Providers\MteThomsonPartSearchProvider;

class PartSearchProviderRegistry
{
    /**
     * Provedores de busca ao vivo disponíveis — cada entrada vira uma opção
     * selecionável no formulário de Fabricante (ver ManufacturerForm), com o
     * slug escolhido salvo em manufacturers.part_search_slug. Deliberadamente
     * desacoplado de Manufacturer.slug (a identidade/URL do fabricante) —
     * renomear o fabricante nunca deve quebrar a busca ao vivo silenciosamente.
     *
     * @var array<string, array{name: string, class: class-string<PartSearchProvider>}>
     */
    private const array PROVIDERS = [
        'mte-thomson' => ['name' => 'MTE-Thomson', 'class' => MteThomsonPartSearchProvider::class],
    ];

    public function for(Manufacturer $manufacturer): ?PartSearchProvider
    {
        if ($manufacturer->part_search_slug === null) {
            return null;
        }

        $entry = self::PROVIDERS[$manufacturer->part_search_slug] ?? null;

        return $entry !== null ? app($entry['class']) : null;
    }

    /**
     * @return array<string, string> slug => name, para popular o Select no ManufacturerForm
     */
    public function options(): array
    {
        return collect(self::PROVIDERS)->map(fn (array $entry): string => $entry['name'])->all();
    }
}
