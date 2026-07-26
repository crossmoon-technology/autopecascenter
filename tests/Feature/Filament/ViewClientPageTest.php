<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\ViewClient;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewClientPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_does_not_appear_in_navigation(): void
    {
        $this->assertFalse(ViewClient::shouldRegisterNavigation());
    }

    public function test_shows_the_clients_name_and_email(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create(['name' => 'João da Oficina', 'email' => 'joao@example.com']);

        Livewire::test(ViewClient::class, ['client' => $client->id])
            ->assertSuccessful()
            ->assertSee('João da Oficina')
            ->assertSee('joao@example.com')
            ->assertSee('O cliente ainda não enviou nenhum pedido.');
    }

    public function test_shows_the_submitted_order_items_quantities_and_manufacturer_preferences_when_present(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();
        $order = Order::factory()->for($client)->for($seller, 'seller')->create();
        $item = $order->items()->create(['description' => 'Pastilha de freio dianteira', 'quantity' => 4]);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap']);
        $item->preferredManufacturers()->attach($manufacturer);

        Livewire::test(ViewClient::class, ['client' => $client->id])
            ->assertSuccessful()
            ->assertSee('Pastilha de freio dianteira')
            ->assertSee('4x')
            ->assertSee('Cofap');
    }

    public function test_shows_every_order_the_client_has_submitted(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();

        $firstOrder = Order::factory()->for($client)->for($seller, 'seller')->create();
        $firstOrder->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        $secondOrder = Order::factory()->for($client)->for($seller, 'seller')->create();
        $secondOrder->items()->create(['description' => 'Correia dentada', 'quantity' => 1]);

        Livewire::test(ViewClient::class, ['client' => $client->id])
            ->assertSuccessful()
            ->assertSee('Pastilha de freio')
            ->assertSee('Correia dentada');
    }

    public function test_shows_the_order_level_comment_when_present(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();
        $order = Order::factory()->for($client)->for($seller, 'seller')->create(['notes' => 'Entregar no período da tarde.']);
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        Livewire::test(ViewClient::class, ['client' => $client->id])
            ->assertSuccessful()
            ->assertSee('Entregar no período da tarde.');
    }

    /**
     * Um cliente vinculado a dois vendedores só mostra pra cada um os pedidos feitos
     * COM ELE — nunca os pedidos que o mesmo cliente fez com outro vendedor.
     */
    public function test_does_not_show_orders_placed_with_another_seller(): void
    {
        $firstSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $secondSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $client = User::factory()->clientOf($firstSeller)->create();
        $client->linkToSeller($secondSeller);

        $orderForSecondSeller = Order::factory()->for($client)->for($secondSeller, 'seller')->create();
        $orderForSecondSeller->items()->create(['description' => 'Peça do outro vendedor', 'quantity' => 1]);

        $this->actingAs($firstSeller);

        Livewire::test(ViewClient::class, ['client' => $client->id])
            ->assertSuccessful()
            ->assertDontSee('Peça do outro vendedor')
            ->assertSee('O cliente ainda não enviou nenhum pedido.');
    }

    public function test_returns_404_for_a_client_not_linked_to_this_seller(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherClient = User::factory()->clientOf($otherSeller)->create();

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->get(ViewClient::getUrl(['client' => $otherClient->id], panel: 'super-admin'))
            ->assertNotFound();
    }
}
