<?php

namespace Tests\Unit\Policies;

use App\Enums\Role;
use App\Models\Catalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro de catálogos é coisa de SuperAdmin — Seller e Client não têm acesso nenhum,
 * mesmo essa resource estando registrada também no painel do vendedor (ver
 * AdminPanelProvider) só pra manter as rotas/URLs consistentes entre os dois painéis.
 */
class CatalogPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_do_everything(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->assertTrue($superAdmin->can('viewAny', Catalog::class));
        $this->assertTrue($superAdmin->can('view', Catalog::class));
        $this->assertTrue($superAdmin->can('create', Catalog::class));
        $this->assertTrue($superAdmin->can('update', Catalog::class));
        $this->assertTrue($superAdmin->can('delete', Catalog::class));
        $this->assertTrue($superAdmin->can('deleteAny', Catalog::class));
        $this->assertTrue($superAdmin->can('restore', Catalog::class));
        $this->assertTrue($superAdmin->can('forceDelete', Catalog::class));
    }

    public function test_seller_cannot_do_anything(): void
    {
        $seller = User::factory()->approvedSeller()->create();

        $this->assertFalse($seller->can('viewAny', Catalog::class));
        $this->assertFalse($seller->can('view', Catalog::class));
        $this->assertFalse($seller->can('create', Catalog::class));
        $this->assertFalse($seller->can('update', Catalog::class));
        $this->assertFalse($seller->can('delete', Catalog::class));
        $this->assertFalse($seller->can('deleteAny', Catalog::class));
        $this->assertFalse($seller->can('restore', Catalog::class));
        $this->assertFalse($seller->can('forceDelete', Catalog::class));
    }

    public function test_client_cannot_do_anything(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);

        $this->assertFalse($client->can('viewAny', Catalog::class));
        $this->assertFalse($client->can('view', Catalog::class));
        $this->assertFalse($client->can('create', Catalog::class));
    }

    /**
     * O ponto real: um vendedor batendo direto na URL do resource (não só o menu
     * escondido) tem que tomar 403 — é isso que Filament faz sozinho com base no
     * canViewAny() da policy, aqui confirmado fim-a-fim.
     */
    public function test_seller_gets_403_hitting_the_resource_route_directly(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $this->actingAs($seller);

        $this->get('/vendedor/catalogs')->assertForbidden();
    }

    public function test_super_admin_can_access_the_resource_route(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($superAdmin);

        $this->get('/super-admin/catalogs')->assertSuccessful();
    }
}
