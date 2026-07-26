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

        $seller = User::factory()->create(['role' => Role::Seller]);
        $seller->preferredManufacturers()->attach($enabled);

        $client = User::factory()->clientOf($seller)->create();
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->assertSee('Cofap')
            ->assertDontSee('Magneti Marelli');
    }

    public function test_falls_back_to_all_active_manufacturers_when_the_seller_has_no_preference(): void
    {
        $one = Manufacturer::factory()->create(['name' => 'Cofap']);
        $another = Manufacturer::factory()->create(['name' => 'Magneti Marelli']);

        $seller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($seller)->create();
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->assertSee($one->name)
            ->assertSee($another->name);
    }

    /**
     * Um cliente com mais de um vendedor vê a união das preferências de todos eles, não
     * só a do primeiro (ver ScopesManufacturersToInvitingSeller::manufacturersScopedToInvitingSeller()).
     */
    public function test_shows_the_union_of_manufacturers_preferred_by_every_linked_seller(): void
    {
        $preferredByFirst = Manufacturer::factory()->create(['name' => 'Cofap']);
        $preferredBySecond = Manufacturer::factory()->create(['name' => 'Hipper Freios']);
        $preferredByNeither = Manufacturer::factory()->create(['name' => 'Magneti Marelli']);

        $firstSeller = User::factory()->create(['role' => Role::Seller]);
        $firstSeller->preferredManufacturers()->attach($preferredByFirst);
        $secondSeller = User::factory()->create(['role' => Role::Seller]);
        $secondSeller->preferredManufacturers()->attach($preferredBySecond);

        $client = User::factory()->clientOf($firstSeller)->create();
        $client->linkToSeller($secondSeller);
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->assertSee('Cofap')
            ->assertSee('Hipper Freios')
            ->assertDontSee('Magneti Marelli');
    }

    public function test_does_not_show_the_seller_filter_when_the_client_has_a_single_seller(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($seller)->create();
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->assertDontSee('Todos os vendedores');
    }

    /**
     * Um cliente com mais de um vendedor pode restringir a lista a só um deles em vez de
     * ver sempre a união de todos (ver Manufacturers::$sellerId).
     */
    public function test_filters_manufacturers_by_a_single_seller_when_chosen(): void
    {
        $preferredByFirst = Manufacturer::factory()->create(['name' => 'Cofap']);
        $preferredBySecond = Manufacturer::factory()->create(['name' => 'Hipper Freios']);

        $firstSeller = User::factory()->create(['role' => Role::Seller, 'name' => 'Loja A']);
        $firstSeller->preferredManufacturers()->attach($preferredByFirst);
        $secondSeller = User::factory()->create(['role' => Role::Seller, 'name' => 'Loja B']);
        $secondSeller->preferredManufacturers()->attach($preferredBySecond);

        $client = User::factory()->clientOf($firstSeller)->create();
        $client->linkToSeller($secondSeller);
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->assertSee('Loja A')
            ->assertSee('Loja B')
            ->set('sellerId', $firstSeller->id)
            ->assertSee('Cofap')
            ->assertDontSee('Hipper Freios');
    }

    public function test_seller_filter_falls_back_to_the_union_when_cleared(): void
    {
        $preferredByFirst = Manufacturer::factory()->create(['name' => 'Cofap']);
        $preferredBySecond = Manufacturer::factory()->create(['name' => 'Hipper Freios']);

        $firstSeller = User::factory()->create(['role' => Role::Seller]);
        $firstSeller->preferredManufacturers()->attach($preferredByFirst);
        $secondSeller = User::factory()->create(['role' => Role::Seller]);
        $secondSeller->preferredManufacturers()->attach($preferredBySecond);

        $client = User::factory()->clientOf($firstSeller)->create();
        $client->linkToSeller($secondSeller);
        $this->actingAs($client);

        Livewire::test(Manufacturers::class)
            ->set('sellerId', $firstSeller->id)
            ->set('sellerId', null)
            ->assertSee('Cofap')
            ->assertSee('Hipper Freios');
    }
}
