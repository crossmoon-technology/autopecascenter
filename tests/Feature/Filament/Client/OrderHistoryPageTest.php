<?php

namespace Tests\Feature\Filament\Client;

use App\Enums\Role;
use App\Filament\Client\Pages\OrderHistory;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_label_is_historico_de_pedidos(): void
    {
        $this->assertSame('Histórico de pedidos', OrderHistory::getNavigationLabel());
    }

    public function test_shows_an_empty_state_with_no_orders_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertSee('Nenhum pedido enviado ainda.');
    }

    public function test_shows_previous_orders_with_quantities_and_manufacturer_preferences(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->create();
        $order->items()->create(['description' => 'Filtro de óleo', 'quantity' => 3]);
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertSee('Filtro de óleo')
            ->assertSee('3x');
    }

    public function test_shows_the_order_level_comment_when_present(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->create(['notes' => 'Entregar no período da tarde.']);
        $order->items()->create(['description' => 'Filtro de óleo', 'quantity' => 1]);
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertSee('Entregar no período da tarde.');
    }

    public function test_does_not_show_another_users_orders(): void
    {
        $otherUser = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($otherUser)->create();
        $order->items()->create(['description' => 'Peça de outro cliente', 'quantity' => 1]);

        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertDontSee('Peça de outro cliente');
    }

    public function test_shows_cancel_button_for_a_pending_order(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->create();
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertSee('Cancelar pedido');
    }

    public function test_shows_cancel_button_for_a_processing_order(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->processing()->create();
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertSee('Cancelar pedido');
    }

    public function test_does_not_show_cancel_button_for_a_finished_order(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->finished()->create();
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertDontSee('Cancelar pedido');
    }

    public function test_does_not_show_cancel_button_for_an_already_cancelled_order(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->cancelled()->create();
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertDontSee('Cancelar pedido');
    }

    public function test_cancel_order_sets_the_status_to_cancelled(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->create();
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->call('cancelOrder', $order->id)
            ->assertNotified()
            ->assertDispatched('order-cancelled', orderId: $order->id);

        $this->assertSame(Status::Cancelled, $order->fresh()->status);
    }

    public function test_each_order_row_carries_a_data_attribute_with_its_id(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->create();
        $order->items()->create(['description' => 'Filtro de óleo', 'quantity' => 1]);
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertSeeHtml('data-order-row="'.$order->id.'"');
    }

    public function test_cancel_order_refuses_to_cancel_a_finished_order(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($user)->finished()->create();
        $this->actingAs($user);

        Livewire::test(OrderHistory::class)
            ->call('cancelOrder', $order->id);

        $this->assertSame(Status::Finished, $order->fresh()->status);
    }

    public function test_cancel_order_refuses_to_cancel_another_users_order(): void
    {
        $otherUser = User::factory()->create(['role' => Role::Client]);
        $order = Order::factory()->for($otherUser)->create();

        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(OrderHistory::class)
            ->call('cancelOrder', $order->id);

        $this->assertSame(Status::Pending, $order->fresh()->status);
    }
}
