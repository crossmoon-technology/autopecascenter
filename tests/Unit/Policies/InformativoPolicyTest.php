<?php

namespace Tests\Unit\Policies;

use App\Enums\Role;
use App\Models\Informativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Diferente das outras 3 policies de cadastro (Catalog/Manufacturer/Part), aqui o
 * vendedor PODE ver os informativos — só as ações de escrita (criar/editar/apagar)
 * ficam restritas ao SuperAdmin.
 */
class InformativoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_do_everything(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->assertTrue($superAdmin->can('viewAny', Informativo::class));
        $this->assertTrue($superAdmin->can('view', Informativo::class));
        $this->assertTrue($superAdmin->can('create', Informativo::class));
        $this->assertTrue($superAdmin->can('update', Informativo::class));
        $this->assertTrue($superAdmin->can('delete', Informativo::class));
        $this->assertTrue($superAdmin->can('deleteAny', Informativo::class));
        $this->assertTrue($superAdmin->can('restore', Informativo::class));
        $this->assertTrue($superAdmin->can('forceDelete', Informativo::class));
    }

    public function test_seller_can_view_but_not_write(): void
    {
        $seller = User::factory()->approvedSeller()->create();

        $this->assertTrue($seller->can('viewAny', Informativo::class));
        $this->assertTrue($seller->can('view', Informativo::class));
        $this->assertFalse($seller->can('create', Informativo::class));
        $this->assertFalse($seller->can('update', Informativo::class));
        $this->assertFalse($seller->can('delete', Informativo::class));
        $this->assertFalse($seller->can('deleteAny', Informativo::class));
    }

    public function test_client_cannot_do_anything(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);

        $this->assertFalse($client->can('viewAny', Informativo::class));
        $this->assertFalse($client->can('view', Informativo::class));
        $this->assertFalse($client->can('create', Informativo::class));
    }

    public function test_seller_can_access_the_resource_route_read_only(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $this->actingAs($seller);

        $this->get('/vendedor/informativos')->assertSuccessful();
    }

    public function test_client_gets_403_hitting_the_resource_route_directly(): void
    {
        // Informativos não têm rota no painel client — o ponto aqui é confirmar que a
        // policy sozinha já bloquearia se algum dia essa resource fosse exposta lá.
        $client = User::factory()->create(['role' => Role::Client]);

        $this->assertFalse($client->can('viewAny', Informativo::class));
    }
}
