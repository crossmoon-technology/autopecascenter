<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/assinatura-vencida');

        $response->assertRedirect(route('login'));
    }

    public function test_shows_the_notice_to_a_seller_with_an_expired_subscription(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'trial_ends_at' => now()->subDays(40),
            'subscription_ends_at' => now()->subDay(),
        ]);
        $this->actingAs($user);

        $response = $this->get('/assinatura-vencida');

        $response->assertOk();
        $response->assertSee('Sua assinatura venceu');
        $response->assertSee('Profissional');
    }

    public function test_a_seller_with_an_active_subscription_is_redirected_to_the_panel(): void
    {
        $user = User::factory()->approvedSeller()->create();
        $this->actingAs($user);

        $response = $this->get('/assinatura-vencida');

        $response->assertRedirect('/vendedor');
    }

    public function test_a_client_is_redirected_to_their_own_panel_instead(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        $response = $this->get('/assinatura-vencida');

        $response->assertRedirect('/cliente');
    }
}
