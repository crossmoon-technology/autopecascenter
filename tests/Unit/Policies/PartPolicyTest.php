<?php

namespace Tests\Unit\Policies;

use App\Enums\Role;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro/edição de peças é coisa de SuperAdmin — buscar peças continua liberado pra
 * todo mundo via Buscas (App\Filament\Pages\Buscas\*), isso aqui é só o resource de
 * cadastro.
 */
class PartPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_do_everything(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->assertTrue($superAdmin->can('viewAny', Part::class));
        $this->assertTrue($superAdmin->can('view', Part::class));
        $this->assertTrue($superAdmin->can('create', Part::class));
        $this->assertTrue($superAdmin->can('update', Part::class));
        $this->assertTrue($superAdmin->can('delete', Part::class));
        $this->assertTrue($superAdmin->can('deleteAny', Part::class));
        $this->assertTrue($superAdmin->can('restore', Part::class));
        $this->assertTrue($superAdmin->can('forceDelete', Part::class));
    }

    public function test_seller_cannot_do_anything(): void
    {
        $seller = User::factory()->approvedSeller()->create();

        $this->assertFalse($seller->can('viewAny', Part::class));
        $this->assertFalse($seller->can('view', Part::class));
        $this->assertFalse($seller->can('create', Part::class));
        $this->assertFalse($seller->can('update', Part::class));
        $this->assertFalse($seller->can('delete', Part::class));
    }

    public function test_client_cannot_do_anything(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);

        $this->assertFalse($client->can('viewAny', Part::class));
        $this->assertFalse($client->can('create', Part::class));
    }

    public function test_seller_gets_403_hitting_the_resource_route_directly(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $this->actingAs($seller);

        $this->get('/vendedor/parts')->assertForbidden();
    }

    public function test_super_admin_can_access_the_resource_route(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($superAdmin);

        $this->get('/super-admin/parts')->assertSuccessful();
    }
}
