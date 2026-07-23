<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\OrderLinks;
use App\Filament\Pages\Buscas\ViewOrderLink;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderLinksPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_vendas_navigation_group(): void
    {
        $this->assertSame('Vendas', OrderLinks::getNavigationGroup());
    }

    public function test_navigation_label_is_clientes(): void
    {
        $this->assertSame('Clientes', OrderLinks::getNavigationLabel());
    }

    public function test_shows_an_empty_state_when_the_user_has_no_links_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(OrderLinks::class)
            ->assertSuccessful()
            ->assertSee('Nenhum cliente cadastrado ainda');
    }

    public function test_does_not_show_another_users_links(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherUser->orderLinks()->create(['label' => 'Link secreto', 'token' => 'secret-token']);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(OrderLinks::class)
            ->assertSuccessful()
            ->assertDontSee('Link secreto');
    }

    public function test_create_action_creates_a_new_link_without_a_token_yet(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(OrderLinks::class)
            ->callTableAction('create', data: ['label' => 'Oficina do João'])
            ->assertNotified();

        $link = $user->orderLinks()->first();
        $this->assertSame('Oficina do João', $link->label);
        $this->assertNull($link->token);
    }

    public function test_create_direct_action_registers_a_client_immediately_without_any_link(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(OrderLinks::class)
            ->callTableAction('createDirect', data: [
                'name' => 'Maria Peça',
                'email' => 'maria@example.com',
                'document' => '12345678901',
            ])
            ->assertNotified();

        $link = $user->orderLinks()->first();
        $this->assertNotNull($link);
        $this->assertNull($link->token);
        $this->assertNotNull($link->used_at);

        $client = $link->registeredUser;
        $this->assertNotNull($client);
        $this->assertSame('Maria Peça', $client->name);
        $this->assertSame('maria@example.com', $client->email);
        $this->assertSame(Role::Client, $client->role);
        $this->assertSame($user->id, $client->invited_by_id);
    }

    public function test_create_direct_action_rejects_a_duplicate_email(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        User::factory()->create(['email' => 'maria@example.com']);

        Livewire::test(OrderLinks::class)
            ->callTableAction('createDirect', data: [
                'name' => 'Maria Peça',
                'email' => 'maria@example.com',
                'document' => '12345678901',
            ])
            ->assertHasTableActionErrors(['email']);
    }

    public function test_unnamed_link_displays_a_fallback_label_with_its_id(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $link = $user->orderLinks()->create(['token' => 'abc123']);

        Livewire::test(OrderLinks::class)
            ->assertSee("Link #{$link->id}");
    }

    public function test_rename_action_updates_the_label(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $link = $user->orderLinks()->create(['label' => 'Antigo', 'token' => 'abc123']);

        Livewire::test(OrderLinks::class)
            ->callTableAction('rename', $link, data: ['label' => 'Novo rótulo']);

        $this->assertSame('Novo rótulo', $link->fresh()->label);
    }

    public function test_delete_action_removes_the_link(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $link = $user->orderLinks()->create(['token' => 'abc123']);

        Livewire::test(OrderLinks::class)
            ->callTableAction('delete', $link)
            ->assertNotified();

        $this->assertDatabaseMissing('order_links', ['id' => $link->id]);
    }

    public function test_clicking_a_link_links_to_its_dedicated_page(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $link = $user->orderLinks()->create(['token' => 'abc123']);

        $url = Livewire::test(OrderLinks::class)->instance()->getTable()->getRecordUrl($link);

        $this->assertSame(ViewOrderLink::getUrl(['orderLink' => $link->id]), $url);
    }

    public function test_shows_the_registration_status_badge(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        $user->orderLinks()->create(['token' => 'open-token']);

        $registeredClient = User::factory()->create(['role' => Role::Client]);
        $user->orderLinks()->create([
            'token' => 'used-token',
            'used_at' => now(),
            'registered_user_id' => $registeredClient->id,
        ]);

        Livewire::test(OrderLinks::class)
            ->assertSee('Aguardando cadastro')
            ->assertSee('Cadastrado');
    }

    public function test_shows_the_pending_orders_count(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        $clientWithPendingOrders = User::factory()->create(['role' => Role::Client]);
        Order::factory()->for($clientWithPendingOrders)->create();
        Order::factory()->for($clientWithPendingOrders)->processing()->create();
        Order::factory()->for($clientWithPendingOrders)->finished()->create();
        $user->orderLinks()->create([
            'token' => 'pending-token',
            'used_at' => now(),
            'registered_user_id' => $clientWithPendingOrders->id,
        ]);

        $clientWithNoPendingOrders = User::factory()->create(['role' => Role::Client]);
        Order::factory()->for($clientWithNoPendingOrders)->finished()->create();
        $user->orderLinks()->create([
            'token' => 'no-pending-token',
            'used_at' => now(),
            'registered_user_id' => $clientWithNoPendingOrders->id,
        ]);

        Livewire::test(OrderLinks::class)
            ->assertSee('2')
            ->assertSee('0');
    }
}
