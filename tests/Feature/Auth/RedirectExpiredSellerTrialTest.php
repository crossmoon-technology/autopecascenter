<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectExpiredSellerTrialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Continua autenticado — só não acessa o painel enquanto não escolher um plano de
     * novo (ver AuthController::login() pro mesmo comportamento já na hora de logar).
     */
    public function test_redirects_to_choose_plan_without_logging_out_when_the_trial_expires_mid_session(): void
    {
        $user = User::factory()->inTrial(Plan::Profissional)->create(['trial_ends_at' => now()->addMinute()]);
        $this->actingAs($user);

        $this->travel(2)->minutes();

        $response = $this->get('/vendedor');

        $response->assertRedirect(route('choose-plan'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_does_not_interfere_with_a_seller_whose_trial_is_still_active(): void
    {
        $user = User::factory()->inTrial(Plan::Profissional)->create(['trial_ends_at' => now()->addDays(3)]);
        $this->actingAs($user);

        $response = $this->get('/vendedor');

        $response->assertSuccessful();
        $this->assertAuthenticatedAs($user);
    }

    public function test_does_not_interfere_with_a_seller_whose_payment_was_already_approved(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create(['trial_ends_at' => now()->subDay()]);
        $this->actingAs($user);

        $response = $this->get('/vendedor');

        $response->assertSuccessful();
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Diferente da avaliação vencida (manda pra escolha de plano) — assinatura paga
     * vencida manda pra uma tela própria, já que o vendedor não escolhe plano de novo,
     * só espera a renovação manual do SuperAdmin (ver SubscriptionStatusController).
     */
    public function test_redirects_to_subscription_expired_without_logging_out_when_the_subscription_expires_mid_session(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'trial_ends_at' => now()->subDays(40),
            'subscription_ends_at' => now()->addMinute(),
        ]);
        $this->actingAs($user);

        $this->travel(2)->minutes();

        $response = $this->get('/vendedor');

        $response->assertRedirect(route('subscription-expired'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Caso raro mas real: SuperAdmin estende trial_ends_at manualmente pra além da
     * assinatura vencida (ver SellerForm) — hasActiveSellerAccess() libera acesso via o
     * trial mesmo assim, e o middleware precisa concordar com isso em vez de travar em
     * "assinatura vencida" só por checar isSubscriptionExpired() isolado.
     */
    public function test_grants_access_via_an_extended_trial_even_with_an_expired_subscription(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'trial_ends_at' => now()->addDays(3),
            'subscription_ends_at' => now()->subDay(),
        ]);
        $this->actingAs($user);

        $response = $this->get('/vendedor');

        $response->assertSuccessful();
        $this->assertAuthenticatedAs($user);
    }
}
