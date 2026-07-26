<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Http\Controllers\AuthController\Exceptions\EmailNotVerifiedException;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_the_panel_matching_the_user_role(): void
    {
        $user = User::factory()->create([
            'role' => Role::Client,
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/cliente');
    }

    public function test_login_ignores_a_stale_intended_url_from_a_different_panel(): void
    {
        // Simulates what happens after logging out of a panel: Filament's own
        // logout redirects back to that panel's root, which — now that the
        // session is guest again — bounces through the login redirect and
        // stores that panel's URL as `url.intended`.
        $this->get('/vendedor');

        $user = User::factory()->create([
            'role' => Role::Client,
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/cliente');
    }

    public function test_an_approved_seller_logs_in_and_reaches_the_admin_panel(): void
    {
        $user = User::factory()->approvedSeller()->create(['password' => bcrypt('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/vendedor');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_seller_with_no_plan_chosen_is_redirected_to_the_choose_plan_page(): void
    {
        $user = User::factory()->seller()->create(['password' => bcrypt('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('choose-plan'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_seller_with_an_unverified_email_is_blocked_with_an_explanatory_message(): void
    {
        $user = User::factory()->seller()->unverified()->create(['password' => bcrypt('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => EmailNotVerifiedException::MESSAGE]);
        $this->assertGuest();
    }

    /**
     * Diferente do e-mail não confirmado (que desloga), aqui a conta continua
     * autenticada — só sem acesso a nenhuma funcionalidade do painel (ver
     * RedirectExpiredSellerTrialTest) — e cai direto na escolha de plano pra poder
     * resolver a situação.
     */
    public function test_a_seller_with_an_expired_trial_and_unapproved_payment_logs_in_but_is_sent_to_choose_a_plan(): void
    {
        $user = User::factory()->inTrial(Plan::Profissional)->create([
            'password' => bcrypt('password'),
            'trial_ends_at' => now()->subDay(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('choose-plan'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_seller_with_an_expired_trial_but_approved_payment_logs_in_normally(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'password' => bcrypt('password'),
            'trial_ends_at' => now()->subDay(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/vendedor');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_seller_with_an_expired_subscription_logs_in_but_is_sent_to_the_subscription_expired_page(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'password' => bcrypt('password'),
            'trial_ends_at' => now()->subDays(40),
            'subscription_ends_at' => now()->subDay(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('subscription-expired'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Mesmo caso do RedirectExpiredSellerTrialTest, mas já na hora de logar — o trial
     * estendido manualmente ainda libera acesso mesmo com a assinatura vencida.
     */
    public function test_a_seller_with_an_extended_trial_logs_in_normally_despite_an_expired_subscription(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'password' => bcrypt('password'),
            'trial_ends_at' => now()->addDays(3),
            'subscription_ends_at' => now()->subDay(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/vendedor');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_seller_still_within_the_trial_period_logs_in_normally(): void
    {
        $user = User::factory()->inTrial(Plan::Profissional)->create([
            'password' => bcrypt('password'),
            'trial_ends_at' => now()->addDays(3),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/vendedor');
        $this->assertAuthenticatedAs($user);
    }
}
