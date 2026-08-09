<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Manufacturers\Pages\CreateManufacturer;
use App\Filament\Resources\Manufacturers\Pages\EditManufacturer;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManufacturerPartSearchSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_manufacturer_with_a_part_search_slug_persists_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'MTE-Thomson',
                'slug' => 'mte-thomson',
                'part_search_slug' => 'mte-thomson',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('mte-thomson', Manufacturer::query()->latest('id')->first()->part_search_slug);
    }

    public function test_part_search_slug_is_optional(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Fabricante Sem Busca Ao Vivo',
                'slug' => 'sem-busca-ao-vivo',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Manufacturer::query()->latest('id')->first()->part_search_slug);
    }

    /**
     * Cada provedor de busca ao vivo só pode ser usado por um fabricante — igual
     * ao scraper_slug do Catálogo, evita dois cadastros disputando a mesma
     * integração.
     */
    public function test_part_search_slug_must_be_unique(): void
    {
        Manufacturer::factory()->create(['part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Outro Fabricante',
                'slug' => 'outro-fabricante',
                'part_search_slug' => 'mte-thomson',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['part_search_slug' => 'unique']);
    }

    public function test_editing_keeps_its_own_part_search_slug_without_triggering_the_unique_rule(): void
    {
        $manufacturer = Manufacturer::factory()->create(['part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditManufacturer::class, ['record' => $manufacturer->getKey()])
            ->fillForm(['name' => 'MTE-Thomson Renomeada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('mte-thomson', $manufacturer->refresh()->part_search_slug);
    }
}
