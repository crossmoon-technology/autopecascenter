<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Iframes;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IframesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_a_tab_for_each_active_manufacturer_with_an_iframe_url(): void
    {
        $manufacturer = Manufacturer::factory()->create([
            'is_active' => true,
            'iframe_url' => 'https://mmcofap.com.br/busca-catalogo/',
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Iframes::class)
            ->assertSuccessful()
            ->assertSee($manufacturer->name)
            ->assertSeeHtml($manufacturer->iframe_url);
    }

    public function test_hides_manufacturers_without_an_iframe_url(): void
    {
        $withoutIframe = Manufacturer::factory()->create(['is_active' => true, 'iframe_url' => null]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Iframes::class)->assertDontSee($withoutIframe->name);
    }

    public function test_hides_inactive_manufacturers_even_with_an_iframe_url(): void
    {
        $inactive = Manufacturer::factory()->create([
            'is_active' => false,
            'iframe_url' => 'https://example.com/busca',
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Iframes::class)->assertDontSee($inactive->name);
    }

    public function test_shows_an_empty_state_when_no_manufacturer_has_an_iframe(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Iframes::class)
            ->assertSee('Nenhum fabricante com iframe cadastrado ainda.');
    }

    public function test_all_manufacturers_with_an_iframe_start_selected(): void
    {
        $a = Manufacturer::factory()->create(['is_active' => true, 'iframe_url' => 'https://a.example.com']);
        $b = Manufacturer::factory()->create(['is_active' => true, 'iframe_url' => 'https://b.example.com']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Iframes::class)
            ->assertSet("manufacturers.{$a->id}", true)
            ->assertSet("manufacturers.{$b->id}", true);
    }

    public function test_deselecting_a_manufacturer_only_affects_its_own_entry(): void
    {
        $a = Manufacturer::factory()->create(['is_active' => true, 'iframe_url' => 'https://a.example.com']);
        $b = Manufacturer::factory()->create(['is_active' => true, 'iframe_url' => 'https://b.example.com']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Iframes::class)
            ->set("manufacturers.{$a->id}", false)
            ->assertSet("manufacturers.{$a->id}", false)
            ->assertSet("manufacturers.{$b->id}", true);
    }
}
