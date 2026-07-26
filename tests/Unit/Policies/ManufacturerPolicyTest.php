<?php

namespace Tests\Unit\Policies;

use App\Enums\Role;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro de fabricantes é coisa de SuperAdmin — não confundir com
 * App\Filament\Pages\Configuracoes\ManufacturerPreferences (onde o vendedor escolhe
 * quais fabricantes já cadastrados aparecem pros clientes dele), que continua liberada.
 */
class ManufacturerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_do_everything(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->assertTrue($superAdmin->can('viewAny', Manufacturer::class));
        $this->assertTrue($superAdmin->can('view', Manufacturer::class));
        $this->assertTrue($superAdmin->can('create', Manufacturer::class));
        $this->assertTrue($superAdmin->can('update', Manufacturer::class));
        $this->assertTrue($superAdmin->can('delete', Manufacturer::class));
        $this->assertTrue($superAdmin->can('deleteAny', Manufacturer::class));
        $this->assertTrue($superAdmin->can('restore', Manufacturer::class));
        $this->assertTrue($superAdmin->can('forceDelete', Manufacturer::class));
    }

    public function test_seller_cannot_do_anything(): void
    {
        $seller = User::factory()->approvedSeller()->create();

        $this->assertFalse($seller->can('viewAny', Manufacturer::class));
        $this->assertFalse($seller->can('view', Manufacturer::class));
        $this->assertFalse($seller->can('create', Manufacturer::class));
        $this->assertFalse($seller->can('update', Manufacturer::class));
        $this->assertFalse($seller->can('delete', Manufacturer::class));
    }

    public function test_client_cannot_do_anything(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);

        $this->assertFalse($client->can('viewAny', Manufacturer::class));
        $this->assertFalse($client->can('create', Manufacturer::class));
    }

    public function test_seller_gets_403_hitting_the_resource_route_directly(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $this->actingAs($seller);

        $this->get('/vendedor/manufacturers')->assertForbidden();
    }

    public function test_super_admin_can_access_the_resource_route(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($superAdmin);

        $this->get('/super-admin/manufacturers')->assertSuccessful();
    }
}
