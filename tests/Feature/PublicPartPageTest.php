<?php

namespace Tests\Feature;

use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicPartPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_part_without_requiring_login(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'name' => 'Suspensão', 'is_active' => true]);
        $part = Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16002',
            'atributos' => ['descricao' => 'MOLA A GÁS'],
            'conversoes' => [],
        ]);

        $response = $this->get($part->publicShareUrl());

        $response->assertOk();
        $response->assertSee('16002');
        $response->assertSee('Cofap');
        $response->assertSee('Suspensão');
        $response->assertSee('MOLA A GÁS');
    }

    public function test_does_not_expose_any_panel_or_favoriting_ui(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $response = $this->get($part->publicShareUrl());

        $response->assertOk();
        $response->assertDontSee('toggleFavorite', false);
        $response->assertDontSee('wire:click', false);
        $response->assertDontSee('fi-sidebar', false);
    }

    public function test_rejects_a_tampered_url(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $otherPart = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $tamperedUrl = str_replace(
            "/p/{$part->id}?",
            "/p/{$otherPart->id}?",
            $part->publicShareUrl(),
        );

        $this->get($tamperedUrl)->assertForbidden();
    }

    public function test_rejects_a_request_with_no_signature_at_all(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $this->get("/p/{$part->id}")->assertForbidden();
    }

    public function test_expired_links_stop_working(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $url = URL::temporarySignedRoute('parts.public', now()->addMinute(), ['part' => $part->id]);

        $this->travel(2)->minutes();

        $this->get($url)->assertForbidden();
    }

    public function test_returns_404_when_the_catalog_is_inactive(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => false]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $this->get($part->publicShareUrl())->assertNotFound();
    }

    public function test_returns_404_when_the_manufacturer_is_inactive(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => false]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $this->get($part->publicShareUrl())->assertNotFound();
    }

    public function test_returns_404_for_a_part_that_does_not_exist(): void
    {
        $url = URL::temporarySignedRoute('parts.public', now()->addDays(90), ['part' => 999999]);

        $this->get($url)->assertNotFound();
    }
}
