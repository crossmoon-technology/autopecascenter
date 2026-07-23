<?php

namespace Tests\Feature\Livewire;

use App\Enums\Role;
use App\Livewire\LgpdConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LgpdConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_visible_for_a_user_who_has_not_accepted_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client, 'lgpd_accepted_at' => null]));

        Livewire::test(LgpdConsent::class)
            ->assertSuccessful()
            ->assertSee('Bem-vindo(a)')
            ->assertSee('Aceitar e continuar');
    }

    public function test_is_not_visible_for_a_user_who_already_accepted(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client, 'lgpd_accepted_at' => now()]));

        Livewire::test(LgpdConsent::class)
            ->assertSuccessful()
            ->assertDontSee('Bem-vindo(a)');
    }

    public function test_accept_records_the_timestamp_hides_the_modal_and_notifies_the_tutorial(): void
    {
        $user = User::factory()->create(['role' => Role::Client, 'lgpd_accepted_at' => null]);
        $this->actingAs($user);

        Livewire::test(LgpdConsent::class)
            ->call('accept')
            ->assertDispatched('lgpd-accepted')
            ->assertSet('visible', false);

        $this->assertNotNull($user->fresh()->lgpd_accepted_at);
    }
}
