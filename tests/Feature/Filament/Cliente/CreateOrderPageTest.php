<?php

namespace Tests\Feature\Filament\Cliente;

use App\Enums\Role;
use App\Filament\Pages\Cliente\CreateOrder;
use App\Models\Order;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateOrderPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Simula uma conta que nasceu Role::Client (vinculada a um vendedor) e depois virou
     * Role::Seller (ver AuthService::registerSeller()) — o cenário real é feito pelo
     * fluxo de cadastro, aqui só o resultado final importa pros testes desta página.
     */
    private function clientTurnedSeller(User $sellerForClient): User
    {
        $seller = User::factory()->clientOf($sellerForClient)->create();

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

        $this->assertFalse(CreateOrder::shouldRegisterNavigation());
    }

    public function test_navigation_is_visible_for_a_seller_that_used_to_be_a_client(): void
    {
        $sellerForClient = User::factory()->create(['role' => Role::Seller]);
        $this->actingAs($this->clientTurnedSeller($sellerForClient));

        $this->assertTrue(CreateOrder::shouldRegisterNavigation());
    }

    /**
     * O pedido continua sendo criado com o mesmo user_id de sempre — é a mesma conta,
     * só o role mudou.
     */
    public function test_submitting_creates_the_order_under_the_same_account(): void
    {
        $sellerForClient = User::factory()->create(['role' => Role::Seller]);
        $seller = $this->clientTurnedSeller($sellerForClient);
        $this->actingAs($seller);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Vela de ignição', 'quantity' => 1, 'manufacturer_ids' => []],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order = Order::query()->where('user_id', $seller->id)->firstOrFail();
        $this->assertSame($sellerForClient->id, $order->seller_id);
    }
}
