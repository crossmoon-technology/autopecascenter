<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Manufacturers\Pages\ListManufacturers;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManufacturerIsActiveToggleColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_activates_a_manufacturer(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListManufacturers::class)
            ->call('updateTableColumnState', 'is_active', (string) $manufacturer->getKey(), true);

        $this->assertTrue($manufacturer->refresh()->is_active);
    }

    public function test_toggle_deactivates_a_manufacturer(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListManufacturers::class)
            ->call('updateTableColumnState', 'is_active', (string) $manufacturer->getKey(), false);

        $this->assertFalse($manufacturer->refresh()->is_active);
    }
}
