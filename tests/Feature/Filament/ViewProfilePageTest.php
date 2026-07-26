<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Perfil\ViewProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_signed_in_users_own_data(): void
    {
        $user = User::factory()->create([
            'role' => Role::Client,
            'name' => 'Maria Peça',
            'email' => 'maria@example.com',
            'document' => '12345678901',
        ]);
        $this->actingAs($user);

        Livewire::test(ViewProfile::class)
            ->assertSuccessful()
            ->assertSee('Maria Peça')
            ->assertSee('maria@example.com')
            ->assertSee('123.456.789-01')
            ->assertSee('Cliente');
    }

    public function test_shows_who_invited_a_client(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller, 'name' => 'Vendedor Um']);
        $client = User::factory()->clientOf($seller)->create();
        $this->actingAs($client);

        Livewire::test(ViewProfile::class)
            ->assertSuccessful()
            ->assertSee('Vendedor Um');
    }

    public function test_does_not_show_invited_by_for_a_seller(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Seller]));

        Livewire::test(ViewProfile::class)
            ->assertSuccessful()
            ->assertDontSee('Convidado por');
    }

    public function test_shows_the_logo_section_only_for_sellers(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ViewProfile::class)
            ->assertSuccessful()
            ->assertSee('Sua logo');
    }

    public function test_does_not_show_the_logo_section_for_a_client(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(ViewProfile::class)
            ->assertSuccessful()
            ->assertDontSee('Sua logo');
    }

    public function test_shows_when_lgpd_terms_were_accepted(): void
    {
        $user = User::factory()->create(['role' => Role::Client, 'lgpd_accepted_at' => now()]);
        $this->actingAs($user);

        Livewire::test(ViewProfile::class)
            ->assertSuccessful()
            ->assertSee('Você aceitou os termos de privacidade');
    }

    public function test_prompts_for_lgpd_acceptance_when_still_pending(): void
    {
        $user = User::factory()->create(['role' => Role::Client, 'lgpd_accepted_at' => null]);
        $this->actingAs($user);

        Livewire::test(ViewProfile::class)
            ->assertSuccessful()
            ->assertSee('ainda não confirmou os termos');
    }

    public function test_lives_under_the_configuracoes_navigation_group(): void
    {
        $this->assertSame('Configurações', ViewProfile::getNavigationGroup());
    }
}
