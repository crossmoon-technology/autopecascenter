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

class ManufacturerSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_name_on_create(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->set('data.name', 'Cofap Autopeças')
            ->assertSet('data.slug', 'cofap-autopecas');
    }

    public function test_slug_stays_editable_after_being_generated(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->set('data.name', 'Cofap Autopeças')
            ->set('data.slug', 'cofap-custom')
            ->assertSet('data.slug', 'cofap-custom');
    }

    public function test_editing_the_name_does_not_change_the_existing_slug(): void
    {
        $manufacturer = Manufacturer::factory()->create([
            'name' => 'Cofap',
            'slug' => 'cofap',
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditManufacturer::class, ['record' => $manufacturer->getKey()])
            ->set('data.name', 'Cofap Renomeada')
            ->assertSet('data.slug', 'cofap');
    }
}
