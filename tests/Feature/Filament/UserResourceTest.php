<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // UserResource só existe no painel super-admin (ver SuperAdminPanelProvider) —
        // sem isso, getUrl() resolve pro painel "admin" (o primeiro registrado em
        // bootstrap/providers.php, usado como default em testes sem uma request HTTP
        // real passando pelo middleware do painel).
        Filament::setCurrentPanel('super-admin');
    }

    public function test_lists_accounts_of_every_role(): void
    {
        $superAdmin = User::factory()->create(['role' => Role::SuperAdmin]);
        $seller = User::factory()->approvedSeller()->create();
        $client = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($superAdmin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$superAdmin, $seller, $client]);
    }

    public function test_is_hidden_from_a_seller_and_a_client(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());
        $this->assertFalse(UserResource::canViewAny());

        $this->actingAs(User::factory()->create(['role' => Role::Client]));
        $this->assertFalse(UserResource::canViewAny());
    }

    public function test_is_visible_to_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->assertTrue(UserResource::canViewAny());
    }

    public function test_role_filter_narrows_down_the_list(): void
    {
        $seller = User::factory()->approvedSeller()->create();
        $client = User::factory()->create(['role' => Role::Client]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListUsers::class)
            ->filterTable('role', Role::Client->value)
            ->assertCanSeeTableRecords([$client])
            ->assertCanNotSeeTableRecords([$seller]);
    }

    public function test_the_create_action_is_visible_on_the_list_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListUsers::class)
            ->assertActionExists('create');
    }

    public function test_the_force_delete_bulk_action_is_available(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListUsers::class)
            ->assertTableBulkActionExists('forceDelete');
    }

    public function test_soft_deleted_users_are_hidden_by_default(): void
    {
        $active = User::factory()->create(['role' => Role::Client]);
        $deleted = User::factory()->create(['role' => Role::Client]);
        $deleted->delete();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$deleted]);
    }
}
