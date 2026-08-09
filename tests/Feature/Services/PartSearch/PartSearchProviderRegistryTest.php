<?php

namespace Tests\Feature\Services\PartSearch;

use App\Models\Manufacturer;
use App\Services\PartSearch\PartSearchProviderRegistry;
use App\Services\PartSearch\Providers\MteThomsonPartSearchProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartSearchProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_mte_thomson_to_its_own_provider(): void
    {
        // De propósito com um slug de identidade diferente — a resolução usa
        // part_search_slug, não Manufacturer.slug (desacoplado: renomear o
        // fabricante nunca quebra a busca ao vivo silenciosamente).
        $manufacturer = Manufacturer::factory()->create(['slug' => 'mte-thomson-brasil', 'part_search_slug' => 'mte-thomson']);

        $provider = (new PartSearchProviderRegistry)->for($manufacturer);

        $this->assertInstanceOf(MteThomsonPartSearchProvider::class, $provider);
    }

    public function test_returns_null_when_part_search_slug_is_not_set(): void
    {
        $manufacturer = Manufacturer::factory()->create(['part_search_slug' => null]);

        $this->assertNull((new PartSearchProviderRegistry)->for($manufacturer));
    }

    public function test_returns_null_for_an_unrecognized_part_search_slug(): void
    {
        $manufacturer = Manufacturer::factory()->create(['part_search_slug' => 'sem-provider']);

        $this->assertNull((new PartSearchProviderRegistry)->for($manufacturer));
    }

    public function test_options_lists_mte_thomson(): void
    {
        $this->assertSame(['mte-thomson' => 'MTE-Thomson'], (new PartSearchProviderRegistry)->options());
    }
}
