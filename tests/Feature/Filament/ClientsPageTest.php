<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Clients;
use App\Filament\Pages\Buscas\ViewClient;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_vendas_navigation_group(): void
    {
        $this->assertSame('Vendas', Clients::getNavigationGroup());
    }

    public function test_navigation_label_is_clientes(): void
    {
        $this->assertSame('Clientes', Clients::getNavigationLabel());
    }

    public function test_shows_an_empty_state_when_the_user_has_no_clients_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Clients::class)
            ->assertSuccessful()
            ->assertSee('Nenhum cliente vinculado ainda');
    }

    public function test_shows_linked_clients_with_name_and_email(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        User::factory()->clientOf($seller)->create(['name' => 'João da Oficina', 'email' => 'joao@example.com']);

        Livewire::test(Clients::class)
            ->assertSuccessful()
            ->assertSee('João da Oficina')
            ->assertSee('joao@example.com');
    }

    public function test_does_not_show_another_sellers_clients(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        User::factory()->clientOf($otherSeller)->create(['name' => 'Cliente de outro vendedor']);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Clients::class)
            ->assertSuccessful()
            ->assertDontSee('Cliente de outro vendedor');
    }

    /**
     * Um cliente vinculado a dois vendedores aparece na lista de ambos — o vínculo N:N
     * não é exclusivo (ver User::clients()).
     */
    public function test_shows_a_client_shared_with_another_seller(): void
    {
        $firstSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $secondSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $client = User::factory()->clientOf($firstSeller)->create(['name' => 'Cliente Compartilhado']);
        $client->linkToSeller($secondSeller);

        $this->actingAs($secondSeller);

        Livewire::test(Clients::class)
            ->assertSuccessful()
            ->assertSee('Cliente Compartilhado');
    }

    public function test_create_direct_action_registers_a_client_and_links_it_immediately(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        Livewire::test(Clients::class)
            ->callTableAction('createDirect', data: [
                'name' => 'Maria Peça',
                'email' => 'maria@example.com',
                'document' => '12345678901',
            ])
            ->assertNotified();

        $client = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('Maria Peça', $client->name);
        $this->assertSame(Role::Client, $client->role);
        $this->assertTrue($client->isLinkedToSeller($seller));
    }

    public function test_create_direct_action_rejects_a_duplicate_email(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        User::factory()->create(['email' => 'maria@example.com']);

        Livewire::test(Clients::class)
            ->callTableAction('createDirect', data: [
                'name' => 'Maria Peça',
                'email' => 'maria@example.com',
                'document' => '12345678901',
            ])
            ->assertHasTableActionErrors(['email']);
    }

    /**
     * O documento é único só entre outros Role::Client — quem já é vendedor em outro
     * lugar pode virar cliente aqui também com o mesmo CPF.
     */
    public function test_create_direct_action_allows_the_same_document_as_an_existing_seller(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        User::factory()->create(['role' => Role::Seller, 'document' => '12345678901']);
        $this->actingAs($seller);

        Livewire::test(Clients::class)
            ->callTableAction('createDirect', data: [
                'name' => 'Maria Peça',
                'email' => 'maria@example.com',
                'document' => '12345678901',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('users', ['document' => '12345678901', 'role' => Role::Client->value]);
    }

    public function test_shows_the_pending_orders_count_scoped_to_this_seller(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $clientWithPendingOrders = User::factory()->clientOf($seller)->create();
        Order::factory()->for($clientWithPendingOrders)->for($seller, 'seller')->create();
        Order::factory()->for($clientWithPendingOrders)->for($seller, 'seller')->processing()->create();
        Order::factory()->for($clientWithPendingOrders)->for($seller, 'seller')->finished()->create();

        $clientWithNoPendingOrders = User::factory()->clientOf($seller)->create();
        Order::factory()->for($clientWithNoPendingOrders)->for($seller, 'seller')->finished()->create();

        Livewire::test(Clients::class)
            ->assertSee('2')
            ->assertSee('0');
    }

    public function test_clicking_a_client_links_to_its_dedicated_page(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();

        $url = Livewire::test(Clients::class)->instance()->getTable()->getRecordUrl($client);

        $this->assertSame(ViewClient::getUrl(['client' => $client->id]), $url);
    }
}
