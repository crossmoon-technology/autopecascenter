<?php

namespace Tests\Feature\Filament\Client;

use App\Enums\Role;
use App\Filament\Client\Pages\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_hidden_from_navigation(): void
    {
        $this->assertFalse(Faq::shouldRegisterNavigation());
    }

    public function test_shows_the_clients_faq_content(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(Faq::class)
            ->assertSuccessful()
            ->assertSee(config('onboarding_faq.client')[0]['question'])
            ->assertDontSee(config('onboarding_faq.seller')[0]['question']);
    }
}
