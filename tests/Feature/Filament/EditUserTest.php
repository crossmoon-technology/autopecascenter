<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EditUserTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'NovaSenha@123';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('super-admin');
    }

    public function test_edit_page_renders_and_prefills_the_existing_data(): void
    {
        $user = User::factory()->create(['name' => 'Cliente Original', 'role' => Role::Client]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertSuccessful()
            ->assertSet('data.name', 'Cliente Original')
            ->assertSet('data.email', $user->email);
    }

    public function test_edit_action_is_visible_on_the_list_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListUsers::class)
            ->assertTableActionExists('edit', record: $user);
    }

    public function test_updates_the_name_without_touching_the_password(): void
    {
        $user = User::factory()->create(['name' => 'Nome Antigo']);
        $originalPassword = $user->password;
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['name' => 'Nome Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Nome Novo', $user->name);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_leaving_the_password_blank_does_not_require_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['password' => '', 'password_confirmation' => ''])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_filling_a_new_password_updates_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_rejects_a_new_password_without_matching_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => 'outra-coisa-Diferente1!',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_promoting_a_client_to_seller_requires_a_plan(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['role' => Role::Seller->value])
            ->call('save')
            ->assertHasFormErrors(['plan']);
    }

    public function test_updates_a_sellers_plan(): void
    {
        $seller = User::factory()->approvedSeller(Plan::Basico)->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $seller->getKey()])
            ->fillForm(['plan' => Plan::Profissional->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Plan::Profissional, $seller->fresh()->plan);
    }

    public function test_does_not_flag_the_records_own_email_and_document_as_duplicates(): void
    {
        $user = User::factory()->create(['name' => 'Original']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['name' => 'Original Editado'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_delete_and_force_delete_actions_are_available(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertActionExists('delete')
            ->assertActionExists('forceDelete');
    }
}
