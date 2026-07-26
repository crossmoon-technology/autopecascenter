<?php

namespace Tests\Feature\Livewire;

use App\Enums\Role;
use App\Filament\Client\Pages\Faq as ClientFaq;
use App\Filament\Pages\Ajuda\Faq as SellerFaq;
use App\Livewire\HelpMenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_to_the_sellers_faq_page_for_an_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Seller]));

        Livewire::test(HelpMenu::class)
            ->assertSuccessful()
            ->assertSeeHtml(SellerFaq::getUrl(panel: 'admin'));
    }

    public function test_links_to_the_sellers_faq_page_for_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(HelpMenu::class)
            ->assertSuccessful()
            ->assertSeeHtml(SellerFaq::getUrl(panel: 'super-admin'));
    }

    public function test_links_to_the_clients_faq_page_for_a_client(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(HelpMenu::class)
            ->assertSuccessful()
            ->assertSeeHtml(ClientFaq::getUrl(panel: 'client'));
    }

    public function test_start_tutorial_dispatches_the_restart_event(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(HelpMenu::class)
            ->call('startTutorial')
            ->assertDispatched('restart-onboarding-tour');
    }
}
