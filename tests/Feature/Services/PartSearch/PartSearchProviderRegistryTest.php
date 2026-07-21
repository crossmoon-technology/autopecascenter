<?php

namespace Tests\Feature\Services\PartSearch;

use App\Models\Manufacturer;
use App\Services\PartSearch\Providers\CofapPartSearchProvider;
use App\Services\PartSearch\Providers\HipperFreiosPartSearchProvider;
use App\Services\PartSearch\PartSearchProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartSearchProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_cofap_and_magneti_marelli_to_the_cofap_provider(): void
    {
        $registry = new PartSearchProviderRegistry;

        $cofap = Manufacturer::factory()->create(['slug' => 'cofap']);
        $magnetiMarelli = Manufacturer::factory()->create(['slug' => 'magneti-marelli']);

        $this->assertInstanceOf(CofapPartSearchProvider::class, $registry->for($cofap));
        $this->assertInstanceOf(CofapPartSearchProvider::class, $registry->for($magnetiMarelli));
    }

    public function test_resolves_hipper_freios_to_its_own_provider(): void
    {
        $registry = new PartSearchProviderRegistry;

        $hipperFreios = Manufacturer::factory()->create(['slug' => 'hipper-freios']);

        $this->assertInstanceOf(HipperFreiosPartSearchProvider::class, $registry->for($hipperFreios));
    }

    public function test_returns_null_for_a_manufacturer_without_a_provider(): void
    {
        $registry = new PartSearchProviderRegistry;

        $wega = Manufacturer::factory()->create(['slug' => 'wega']);

        $this->assertNull($registry->for($wega));
    }
}
