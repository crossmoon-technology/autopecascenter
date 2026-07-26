<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSellerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_with_no_plan_chosen_yet_has_no_access(): void
    {
        $user = User::factory()->seller()->create();

        $this->assertFalse($user->hasActiveSellerAccess());
        $this->assertFalse($user->isPaymentPending());
    }

    public function test_choosing_a_plan_grants_access_automatically_via_trial(): void
    {
        $user = User::factory()->inTrial()->create();

        $this->assertTrue($user->hasActiveSellerAccess());
        $this->assertTrue($user->isPaymentPending());
    }

    public function test_approving_payment_clears_the_pending_state(): void
    {
        $user = User::factory()->inTrial()->create();

        $user->approvePlanPayment();

        $this->assertFalse($user->fresh()->isPaymentPending());
        $this->assertTrue($user->fresh()->hasActiveSellerAccess());
    }

    public function test_a_seller_with_a_future_trial_end_is_not_expired(): void
    {
        $user = User::factory()->inTrial(Plan::Profissional)->create(['trial_ends_at' => now()->addDays(3)]);

        $this->assertFalse($user->isTrialExpired());
        $this->assertTrue($user->hasActiveSellerAccess());
    }

    public function test_a_seller_with_a_past_trial_end_and_no_approved_payment_loses_access(): void
    {
        $user = User::factory()->inTrial(Plan::Profissional)->create(['trial_ends_at' => now()->subDay()]);

        $this->assertTrue($user->isTrialExpired());
        $this->assertFalse($user->hasActiveSellerAccess());
    }

    public function test_approved_payment_keeps_access_even_after_the_trial_expires(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create(['trial_ends_at' => now()->subDay()]);

        $this->assertTrue($user->isTrialExpired());
        $this->assertFalse($user->isPaymentPending());
        $this->assertTrue($user->hasActiveSellerAccess());
    }

    /**
     * Diferente do trial (que era pra sempre uma vez aprovado) — a assinatura vence em 30
     * dias e precisa de nova aprovação do SuperAdmin pra continuar (ver
     * User::approvePlanPayment()).
     */
    public function test_a_seller_with_an_expired_subscription_loses_access_even_with_an_approved_payment(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'trial_ends_at' => now()->subDays(40),
            'subscription_ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($user->isSubscriptionExpired());
        $this->assertFalse($user->hasActiveSellerAccess());
        $this->assertTrue($user->isPaymentPending());
    }

    public function test_approving_payment_again_renews_the_subscription_for_30_more_days(): void
    {
        $user = User::factory()->approvedSeller(Plan::Profissional)->create([
            'trial_ends_at' => now()->subDays(40),
            'subscription_ends_at' => now()->subDay(),
        ]);

        $user->approvePlanPayment();

        $this->assertFalse($user->fresh()->isSubscriptionExpired());
        $this->assertTrue($user->fresh()->hasActiveSellerAccess());
        $this->assertTrue($user->fresh()->subscription_ends_at->isFuture());
    }

    /**
     * Cobre contas aprovadas antes do campo subscription_ends_at existir (nunca tiveram
     * essa data preenchida) — não podem quebrar nem perder acesso só por causa da
     * migration nova (ver sellerStatusLabel()).
     */
    public function test_an_approved_seller_with_no_subscription_end_date_is_treated_as_active_without_expiration(): void
    {
        $user = User::factory()->approvedSeller()->create(['subscription_ends_at' => null]);

        $this->assertFalse($user->isSubscriptionExpired());
        $this->assertTrue($user->hasActiveSellerAccess());
        $this->assertSame('Ativo', $user->sellerStatusLabel());
        $this->assertSame('success', $user->sellerStatusColor());
    }

    public function test_non_seller_roles_never_report_payment_pending_or_trial_expired(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
        $client = User::factory()->create(['role' => Role::Client]);

        $this->assertFalse($superAdmin->isPaymentPending());
        $this->assertFalse($superAdmin->isTrialExpired());
        $this->assertFalse($superAdmin->hasActiveSellerAccess());

        $this->assertFalse($client->isPaymentPending());
        $this->assertFalse($client->isTrialExpired());
        $this->assertFalse($client->hasActiveSellerAccess());
    }

    public function test_status_label_and_color_reflect_each_state(): void
    {
        $unverified = User::factory()->seller()->unverified()->create();
        $this->assertSame('E-mail não confirmado', $unverified->sellerStatusLabel());
        $this->assertSame('gray', $unverified->sellerStatusColor());

        $noPlan = User::factory()->seller()->create();
        $this->assertSame('Aguardando escolha de plano', $noPlan->sellerStatusLabel());
        $this->assertSame('warning', $noPlan->sellerStatusColor());

        $expired = User::factory()->inTrial(Plan::Profissional)->create(['trial_ends_at' => now()->subDay()]);
        $this->assertSame('Avaliação expirada', $expired->sellerStatusLabel());
        $this->assertSame('danger', $expired->sellerStatusColor());

        $trial = User::factory()->inTrial(Plan::Profissional)->create(['trial_ends_at' => now()->addDays(5)]);
        $this->assertStringContainsString('Em avaliação até', $trial->sellerStatusLabel());
        $this->assertSame('info', $trial->sellerStatusColor());

        $active = User::factory()->approvedSeller()->create();
        $this->assertStringContainsString('Ativo até', $active->sellerStatusLabel());
        $this->assertSame('success', $active->sellerStatusColor());

        $subscriptionExpired = User::factory()->approvedSeller(Plan::Profissional)->create([
            'trial_ends_at' => now()->subDays(40),
            'subscription_ends_at' => now()->subDay(),
        ]);
        $this->assertSame('Assinatura vencida', $subscriptionExpired->sellerStatusLabel());
        $this->assertSame('danger', $subscriptionExpired->sellerStatusColor());
    }
}
