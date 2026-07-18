<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogDatabaseSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_manufacturer_chips_and_codigo_field(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertSuccessful()
            ->assertFormFieldExists('codigo')
            ->assertSee($manufacturer->name)
            ->assertSet("data.manufacturers.{$manufacturer->id}", true);
    }

    public function test_inactive_manufacturers_are_not_shown_as_chips(): void
    {
        $inactive = Manufacturer::factory()->create(['is_active' => false]);
        Catalog::factory()->create(['manufacturer_id' => $inactive->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertDontSee($inactive->name);
    }

    public function test_manufacturers_without_any_active_catalog_are_not_shown_as_chips(): void
    {
        $withoutCatalogs = Manufacturer::factory()->create(['is_active' => true]);
        $withOnlyInactiveCatalog = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $withOnlyInactiveCatalog->getKey(), 'is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertDontSee($withoutCatalogs->name)
            ->assertDontSee($withOnlyInactiveCatalog->name);
    }

    public function test_search_requires_at_least_one_manufacturer_selected(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->set('data.codigo', '16088')
            ->call('search')
            ->assertNotified();

        $this->assertDatabaseCount('parts', 0);
    }

    public function test_search_requires_codigo(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->call('search')
            ->assertHasFormErrors(['codigo']);
    }

    public function test_search_does_not_create_or_change_any_records_yet(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16088'])
            ->call('search')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('parts', 0);
    }
}
