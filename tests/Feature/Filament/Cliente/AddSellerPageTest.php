<?php

namespace Tests\Feature\Filament\Cliente;

use App\Enums\Role;
use App\Filament\Pages\Cliente\AddSeller;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddSellerPageTest extends TestCase
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

        $this->assertFalse(AddSeller::shouldRegisterNavigation());
    }

    public function test_navigation_is_visible_for_a_seller_that_used_to_be_a_client(): void
    {
        $sellerForClient = User::factory()->create(['role' => Role::Seller]);
        $this->actingAs($this->clientTurnedSeller($sellerForClient));

        $this->assertTrue(AddSeller::shouldRegisterNavigation());
    }

    /**
     * O código vincula a própria conta logada — é a mesma que já era cliente.
     */
    public function test_links_the_account_to_the_seller_matching_the_code(): void
    {
        $sellerForClient = User::factory()->create(['role' => Role::Seller]);
        $newSeller = User::factory()->create(['role' => Role::Seller]);
        $seller = $this->clientTurnedSeller($sellerForClient);
        $this->actingAs($seller);

        Livewire::test(AddSeller::class)
            ->set('data.referral_code', $newSeller->referral_code)
            ->call('addSeller')
            ->assertNotified();

        $this->assertTrue($seller->fresh()->isLinkedToSeller($newSeller));
    }

    /**
     * Desanexa da própria conta logada — é a mesma que já era cliente.
     */
    public function test_removes_a_seller_from_the_account(): void
    {
        $sellerForClient = User::factory()->create(['role' => Role::Seller]);
        $otherSellerForClient = User::factory()->create(['role' => Role::Seller]);
        $seller = $this->clientTurnedSeller($sellerForClient);
        $seller->linkToSeller($otherSellerForClient);
        $this->actingAs($seller);

        Livewire::test(AddSeller::class)
            ->call('removeSeller', $sellerForClient->id)
            ->assertNotified();

        $this->assertFalse($seller->fresh()->isLinkedToSeller($sellerForClient));
        $this->assertTrue($seller->fresh()->isLinkedToSeller($otherSellerForClient));
    }
}
