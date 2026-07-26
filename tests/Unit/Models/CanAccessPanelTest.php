<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A fronteira mais básica entre os 3 papéis: cada um só acessa o painel que é dele. Sem
 * teste nenhum cobrindo isso até agora — é o tipo de regressão que passaria despercebida
 * (ex: alguém adiciona um painel novo e esquece de restringir o role certo).
 */
class CanAccessPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_only_access_the_super_admin_panel(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->assertTrue($superAdmin->canAccessPanel(filament()->getPanel('super-admin')));
        $this->assertFalse($superAdmin->canAccessPanel(filament()->getPanel('admin')));
        $this->assertFalse($superAdmin->canAccessPanel(filament()->getPanel('client')));
    }

    public function test_approved_seller_can_only_access_the_admin_panel(): void
    {
        $seller = User::factory()->approvedSeller()->create();

        $this->assertFalse($seller->canAccessPanel(filament()->getPanel('super-admin')));
        $this->assertTrue($seller->canAccessPanel(filament()->getPanel('admin')));
        $this->assertFalse($seller->canAccessPanel(filament()->getPanel('client')));
    }

    /**
     * Um vendedor sem plano/avaliação ativa não acessa nem o próprio painel — essa parte
     * já é coberta indiretamente por outros testes de trial/plano, mas confirmar aqui
     * fecha o quadro junto com os outros dois papéis.
     */
    public function test_seller_without_active_access_cannot_access_the_admin_panel(): void
    {
        $seller = User::factory()->seller()->create();

        $this->assertFalse($seller->canAccessPanel(filament()->getPanel('admin')));
    }

    /**
     * Role::Admin é reservado pra um futuro ator (ver App\Enums\Role) — nenhuma conta
     * deve ter esse valor hoje, mas se alguma tiver (dado corrompido, bug de migration),
     * não pode acessar nenhum painel por engano.
     */
    public function test_admin_role_cannot_access_any_panel(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->assertFalse($admin->canAccessPanel(filament()->getPanel('super-admin')));
        $this->assertFalse($admin->canAccessPanel(filament()->getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(filament()->getPanel('client')));
    }

    public function test_client_can_only_access_the_client_panel(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);

        $this->assertFalse($client->canAccessPanel(filament()->getPanel('super-admin')));
        $this->assertFalse($client->canAccessPanel(filament()->getPanel('admin')));
        $this->assertTrue($client->canAccessPanel(filament()->getPanel('client')));
    }

    public function test_seller_hitting_the_super_admin_panel_over_http_is_forbidden(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $this->actingAs($seller);

        $this->get('/super-admin')->assertForbidden();
    }

    public function test_client_hitting_the_admin_panel_over_http_is_forbidden(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($client);

        $this->get('/vendedor')->assertForbidden();
    }

    public function test_client_hitting_the_super_admin_panel_over_http_is_forbidden(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($client);

        $this->get('/super-admin')->assertForbidden();
    }

    public function test_super_admin_hitting_the_client_panel_over_http_is_forbidden(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($superAdmin);

        $this->get('/cliente')->assertForbidden();
    }

    public function test_seller_hitting_the_client_panel_over_http_is_forbidden(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $this->actingAs($seller);

        $this->get('/cliente')->assertForbidden();
    }

    public function test_an_unauthenticated_visitor_is_redirected_to_login_for_any_panel(): void
    {
        $this->get('/vendedor')->assertRedirect(route('login'));
        $this->get('/cliente')->assertRedirect(route('login'));
        $this->get('/super-admin')->assertRedirect(route('login'));
    }
}
