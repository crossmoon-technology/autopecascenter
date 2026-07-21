<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Manufacturers\Pages\CreateManufacturer;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManufacturerIframeUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_a_valid_iframe_url(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Cofap',
                'slug' => 'cofap',
                'iframe_url' => 'https://mmcofap.com.br/busca-catalogo/',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'https://mmcofap.com.br/busca-catalogo/',
            Manufacturer::firstOrFail()->iframe_url
        );
    }

    public function test_accepts_a_null_iframe_url(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Bosch',
                'slug' => 'bosch',
                'iframe_url' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Manufacturer::firstOrFail()->iframe_url);
    }

    public function test_rejects_an_invalid_iframe_url(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Nakata',
                'slug' => 'nakata',
                'iframe_url' => 'not-a-url',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['iframe_url']);
    }
}
