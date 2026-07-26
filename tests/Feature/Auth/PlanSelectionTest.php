<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/escolher-plano');

        $response->assertRedirect(route('login'));
    }

    public function test_shows_the_3_options_to_a_seller_with_no_plan_yet(): void
    {
        $this->actingAs(User::factory()->seller()->create());

        $response = $this->get('/escolher-plano');

        $response->assertOk();
        $response->assertSee('Avaliação gratuita');
        $response->assertSee('Básico');
        $response->assertSee('Profissional');
    }

    public function test_a_client_is_redirected_to_their_own_panel_instead(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        $response = $this->get('/escolher-plano');

        $response->assertRedirect('/cliente');
    }

    public function test_a_seller_who_already_has_a_plan_is_redirected_to_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->inTrial()->create());

        $response = $this->get('/escolher-plano');

        $response->assertRedirect('/vendedor');
    }

    public function test_choosing_the_free_trial_grants_immediate_access_and_starts_the_trial(): void
    {
        $user = User::factory()->seller()->create();
        $this->actingAs($user);

        $response = $this->post('/escolher-plano', ['plan_choice' => 'trial']);

        $response->assertRedirect('/vendedor');
        $user->refresh();
        $this->assertSame(Plan::Profissional, $user->plan);
        $this->assertNotNull($user->trial_ends_at);
        $this->assertTrue($user->trial_ends_at->isFuture());
        $this->assertTrue($user->hasActiveSellerAccess());
    }

    public function test_choosing_basico_also_starts_the_trial_but_shows_the_24h_contact_notice(): void
    {
        $user = User::factory()->seller()->create();
        $this->actingAs($user);

        $response = $this->post('/escolher-plano', ['plan_choice' => 'basico']);

        $response->assertOk();
        $response->assertSee('24 horas');
        $user->refresh();
        $this->assertSame(Plan::Basico, $user->plan);
        $this->assertNotNull($user->trial_ends_at);
        $this->assertTrue($user->hasActiveSellerAccess());
    }

    public function test_choosing_profissional_also_starts_the_trial_but_shows_the_24h_contact_notice(): void
    {
        $user = User::factory()->seller()->create();
        $this->actingAs($user);

        $response = $this->post('/escolher-plano', ['plan_choice' => 'profissional']);

        $response->assertOk();
        $response->assertSee('24 horas');
        $user->refresh();
        $this->assertSame(Plan::Profissional, $user->plan);
        $this->assertNotNull($user->trial_ends_at);
    }

    public function test_rejects_an_invalid_plan_choice(): void
    {
        $this->actingAs(User::factory()->seller()->create());

        $response = $this->post('/escolher-plano', ['plan_choice' => 'empresarial']);

        $response->assertSessionHasErrors('plan_choice');
    }

    public function test_a_seller_who_already_chose_a_plan_cannot_choose_again(): void
    {
        $user = User::factory()->inTrial(Plan::Basico)->create();
        $original_trial_ends_at = $user->trial_ends_at;
        $this->actingAs($user);

        $response = $this->post('/escolher-plano', ['plan_choice' => 'profissional']);

        $response->assertRedirect('/vendedor');
        $user->refresh();
        $this->assertSame(Plan::Basico, $user->plan);
        $this->assertTrue($user->trial_ends_at->equalTo($original_trial_ends_at));
    }

    /**
     * Diferente de "já tem um plano ativo" (bloqueado acima) — vencida e sem pagamento
     * aprovado, a conta consegue chegar em /escolher-plano de novo (ver
     * PlanSelectionController::canChoosePlan()), mas como já tinha escolhido um plano
     * antes, vê o aviso de aprovação pendente em vez do formulário de escolha (ver
     * PlanSelectionController::isAwaitingApproval()).
     */
    public function test_a_seller_with_an_expired_trial_and_no_approved_payment_is_shown_the_pending_approval_notice(): void
    {
        $user = User::factory()->inTrial(Plan::Basico)->create(['trial_ends_at' => now()->subDay()]);
        $this->actingAs($user);

        $response = $this->get('/escolher-plano');

        $response->assertOk();
        $response->assertSee('Aguardando aprovação');
        $response->assertDontSee('Começar avaliação gratuita');
        $response->assertSee('Mudar para Profissional');
        $response->assertDontSee('Mudar para Básico');
    }

    public function test_a_seller_with_an_expired_trial_but_approved_payment_is_redirected_to_the_panel(): void
    {
        $user = User::factory()->approvedSeller(Plan::Basico)->create(['trial_ends_at' => now()->subDay()]);
        $this->actingAs($user);

        $response = $this->get('/escolher-plano');

        $response->assertRedirect('/vendedor');
    }

    /**
     * Defesa em profundidade — mesmo que o formulário de escolha nem seja mostrado nesse
     * estado (ver teste acima), o backend rejeita um POST adulterado tentando reescolher
     * "trial".
     */
    public function test_rejects_choosing_trial_again_after_it_was_already_used(): void
    {
        $user = User::factory()->inTrial(Plan::Basico)->create(['trial_ends_at' => now()->subDay()]);
        $this->actingAs($user);

        $response = $this->post('/escolher-plano', ['plan_choice' => 'trial']);

        $response->assertSessionHasErrors('plan_choice');
        $this->assertSame(Plan::Basico, $user->fresh()->plan);
    }

    /**
     * A avaliação de 7 dias é de uso único por conta — reescolher um plano pago depois de
     * vencida troca o plano registrado (o vendedor pode mudar de ideia enquanto espera),
     * mas não gera mais dias de acesso automático; ele continua bloqueado até o SuperAdmin
     * aprovar o pagamento (ver PlanSelectionController::isAwaitingApproval()).
     */
    public function test_choosing_a_paid_plan_again_after_the_trial_expired_switches_the_plan_but_grants_no_access(): void
    {
        $user = User::factory()->inTrial(Plan::Basico)->create(['trial_ends_at' => now()->subDay()]);
        $original_trial_ends_at = $user->trial_ends_at;
        $this->actingAs($user);

        $response = $this->post('/escolher-plano', ['plan_choice' => 'profissional']);

        $response->assertOk();
        $response->assertSee('Aguardando aprovação');
        $this->assertSame(Plan::Profissional, $user->fresh()->plan);
        $this->assertTrue($user->fresh()->trial_ends_at->equalTo($original_trial_ends_at));
        $this->assertFalse($user->fresh()->hasActiveSellerAccess());
    }
}
