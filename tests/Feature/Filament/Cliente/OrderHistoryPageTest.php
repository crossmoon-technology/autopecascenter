<?php

namespace Tests\Feature\Filament\Cliente;

use App\Enums\Role;
use App\Filament\Pages\Cliente\OrderHistory;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Simula uma conta que nasceu Role::Client (vinculada a um vendedor) e depois virou
     * Role::Seller (ver AuthService::registerSeller()) — o cenário real é feito pelo
     * fluxo de cadastro, aqui só o resultado final importa pros testes desta página.
     */
    private function clientTurnedSeller(): User
    {
        $otherSeller = User::factory()->create(['role' => Role::Seller]);
        $seller = User::factory()->clientOf($otherSeller)->create();

        $seller->forceFill([
            'role' => Role::Seller,
            'plan' => Plan::Basico,
            'trial_ends_at' => now()->subDay(),
            'plan_approved_at' => now(),
        ])->save();

        return $seller->fresh();
    }

    public function test_navigation_is_hidden_for_a_seller_that_was_never_a_client(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->assertFalse(OrderHistory::shouldRegisterNavigation());
    }

    public function test_navigation_is_visible_for_a_seller_that_used_to_be_a_client(): void
    {
        $this->actingAs($this->clientTurnedSeller());

        $this->assertTrue(OrderHistory::shouldRegisterNavigation());
    }

    public function test_shows_the_orders_the_account_placed_back_when_it_was_a_client(): void
    {
        $seller = $this->clientTurnedSeller();
        $order = Order::factory()->for($seller)->create();
        $order->items()->create(['description' => 'Filtro de óleo de quando eu era cliente', 'quantity' => 2]);

        $this->actingAs($seller);

        Livewire::test(OrderHistory::class)
            ->assertSuccessful()
            ->assertSee('Filtro de óleo de quando eu era cliente');
    }

    public function test_seller_can_cancel_an_order_from_when_the_account_was_a_client(): void
    {
        $seller = $this->clientTurnedSeller();
        $order = Order::factory()->for($seller)->create();

        $this->actingAs($seller);

        Livewire::test(OrderHistory::class)->call('cancelOrder', $order->id);

        $this->assertSame(Status::Cancelled, $order->fresh()->status);
    }
}
