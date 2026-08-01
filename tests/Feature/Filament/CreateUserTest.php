<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use App\Models\User\Enums\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'Senha@1234';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('super-admin');
    }

    public function test_creates_a_client(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Client->value,
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
                'document' => '11122233344',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'cliente@example.com')->firstOrFail();
        $this->assertSame(Role::Client, $user->role);
        $this->assertNull($user->plan);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_creates_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::SuperAdmin->value,
                'name' => 'Admin Teste',
                'email' => 'admin@example.com',
                'document' => '55566677788',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => Role::SuperAdmin->value]);
    }

    public function test_creates_a_seller_with_a_plan(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Seller->value,
                'name' => 'Vendedor Teste',
                'email' => 'vendedor@example.com',
                'document' => '99988877766',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
                'plan' => Plan::Profissional->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $seller = User::query()->where('email', 'vendedor@example.com')->firstOrFail();
        $this->assertSame(Role::Seller, $seller->role);
        $this->assertSame(Plan::Profissional, $seller->plan);
        $this->assertNotNull($seller->referral_code);
    }

    public function test_plan_is_required_when_role_is_seller(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Seller->value,
                'name' => 'Vendedor Sem Plano',
                'email' => 'sem-plano@example.com',
                'document' => '12312312312',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->call('create')
            ->assertHasFormErrors(['plan']);
    }

    public function test_rejects_a_weak_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Client->value,
                'name' => 'Cliente Fraco',
                'email' => 'fraco@example.com',
                'document' => '32132132132',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_rejects_mismatched_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Client->value,
                'name' => 'Cliente Confirmação',
                'email' => 'confirmacao@example.com',
                'document' => '45645645645',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => 'Outra@Senha123',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicado@example.com']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Client->value,
                'name' => 'Outro Cliente',
                'email' => 'duplicado@example.com',
                'document' => '78978978978',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_allows_the_same_document_across_different_roles(): void
    {
        User::factory()->create(['role' => Role::Client, 'document' => '11111111111']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Seller->value,
                'name' => 'Vendedor Mesmo Documento',
                'email' => 'mesmo-documento@example.com',
                'document' => '11111111111',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
                'plan' => Plan::Basico->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_rejects_the_same_document_for_the_same_role(): void
    {
        User::factory()->create(['role' => Role::Client, 'document' => '22222222222']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Client->value,
                'name' => 'Outro Cliente Documento',
                'email' => 'outro-documento@example.com',
                'document' => '22222222222',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->call('create')
            ->assertHasFormErrors(['document']);
    }

    public function test_does_not_offer_the_reserved_admin_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'role' => Role::Admin->value,
                'name' => 'Não Deveria Existir',
                'email' => 'nao-deveria@example.com',
                'document' => '65465465465',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->call('create')
            ->assertHasFormErrors(['role']);
    }
}
