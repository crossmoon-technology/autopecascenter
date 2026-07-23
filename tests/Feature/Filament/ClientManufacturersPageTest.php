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

    public function test_lists_only_active_manufacturers_without_a_clickable_link(): void
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
            ->assertDontSeeHtml('href="https://www.cofap.com.br"');
    }

    public function test_shows_only_manufacturers_enabled_by_the_inviting_seller(): void
    {
        $enabled = Manufacturer::factory()->create(['name' => 'Cofap']);
        $notEnabled = Manufacturer::factory()->create(['name' => 'Magneti Marelli']);

        $seller = User::factory()->create(['role' => Role::Admin]);
        $seller->preferredManufacturers()->attach($enabled);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->assertSee('Cofap')
            ->assertDontSee('Magneti Marelli');
    }

    public function test_falls_back_to_all_active_manufacturers_when_the_seller_has_no_preference(): void
    {
        $one = Manufacturer::factory()->create(['name' => 'Cofap']);
        $another = Manufacturer::factory()->create(['name' => 'Magneti Marelli']);

        $seller = User::factory()->create(['role' => Role::Admin]);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->assertSee($one->name)
            ->assertSee($another->name);
    }
}
