<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Ajuda\Faq;
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

    public function test_shows_the_sellers_faq_content_for_an_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        Livewire::test(Faq::class)
            ->assertSuccessful()
            ->assertSee(config('onboarding_faq.seller')[0]['question'])
            ->assertDontSee(config('onboarding_faq.client')[0]['question']);
    }

    public function test_shows_the_sellers_faq_content_for_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Faq::class)
            ->assertSuccessful()
            ->assertSee(config('onboarding_faq.seller')[0]['question']);
    }
}
