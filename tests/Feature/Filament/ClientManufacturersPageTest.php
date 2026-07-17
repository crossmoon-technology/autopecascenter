<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Client\Pages\Manufacturers;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientManufacturersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_active_manufacturers_with_their_external_link(): void
    {
        $active = Manufacturer::factory()->create([
            'name' => 'Cofap',
            'external_link' => 'https://www.cofap.com.br',
            'is_active' => true,
        ]);
        $inactive = Manufacturer::factory()->create([
            'name' => 'Inativa',
            'is_active' => false,
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(Manufacturers::class)
            ->assertSee($active->name)
            ->assertDontSee($inactive->name)
            ->assertSeeHtml('href="https://www.cofap.com.br"');
    }
}
