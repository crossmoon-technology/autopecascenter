<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Manufacturers\Pages\CreateManufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManufacturerExternalLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_a_valid_url(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Cofap',
                'slug' => 'cofap',
                'external_link' => 'https://www.cofap.com.br',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_accepts_a_null_external_link(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Bosch',
                'slug' => 'bosch',
                'external_link' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_rejects_an_invalid_external_link(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateManufacturer::class)
            ->fillForm([
                'name' => 'Nakata',
                'slug' => 'nakata',
                'external_link' => 'not-a-url',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['external_link']);
    }
}
