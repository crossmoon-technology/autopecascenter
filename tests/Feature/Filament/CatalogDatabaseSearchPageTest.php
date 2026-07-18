<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\PartEquivalenceSearch;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartEquivalenceSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_manufacturer_and_codigo_fields(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PartEquivalenceSearch::class)
            ->assertSuccessful()
            ->assertFormFieldExists('manufacturer_id')
            ->assertFormFieldExists('codigo')
            ->assertSee($manufacturer->name);
    }

    public function test_search_requires_manufacturer_and_codigo(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PartEquivalenceSearch::class)
            ->call('search')
            ->assertHasFormErrors(['manufacturer_id', 'codigo']);
    }

    public function test_search_does_not_create_or_change_any_records_yet(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PartEquivalenceSearch::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'codigo' => '16088',
            ])
            ->call('search')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('parts', 0);
    }
}
