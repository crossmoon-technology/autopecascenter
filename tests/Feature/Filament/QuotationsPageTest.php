<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Quotations;
use App\Filament\Pages\Buscas\ViewQuotation;
use App\Models\Quotation\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_buscas_navigation_group(): void
    {
        $this->assertSame('Buscas', Quotations::getNavigationGroup());
    }

    public function test_shows_an_empty_state_when_the_user_has_no_saved_quotations_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Quotations::class)
            ->assertSuccessful()
            ->assertSee('Nenhuma cotação salva ainda');
    }

    public function test_the_open_quotation_does_not_appear_in_the_listing(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $user->quotations()->create(['name' => 'Carrinho atual', 'status' => Status::Open]);

        Livewire::test(Quotations::class)
            ->assertDontSee('Carrinho atual')
            ->assertSee('Nenhuma cotação salva ainda');
    }

    public function test_shows_saved_quotations(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $user->quotations()->create(['name' => 'Orçamento Cliente X', 'status' => Status::Closed]);

        Livewire::test(Quotations::class)
            ->assertSee('Orçamento Cliente X');
    }

    public function test_does_not_show_another_users_saved_quotations(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherUser->quotations()->create(['name' => 'Cotação secreta', 'status' => Status::Closed]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Quotations::class)
            ->assertSuccessful()
            ->assertDontSee('Cotação secreta');
    }

    public function test_unnamed_quotation_displays_a_fallback_name_with_its_id(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Closed]);

        Livewire::test(Quotations::class)
            ->assertSee("Cotação #{$quotation->id}");
    }

    public function test_reopen_action_sets_the_status_back_to_open(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Closed, 'name' => 'Fechada']);

        Livewire::test(Quotations::class)
            ->callTableAction('reopen', $quotation)
            ->assertNotified();

        $this->assertSame(Status::Open, $quotation->fresh()->status);
    }

    public function test_delete_action_removes_the_quotation(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Closed, 'name' => 'Fechada']);

        Livewire::test(Quotations::class)
            ->callTableAction('delete', $quotation)
            ->assertNotified();

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
    }

    public function test_reopening_an_older_quotation_makes_it_the_current_open_one_again(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $stillOpen = $user->quotations()->create(['status' => Status::Open]);
        $reopened = $user->quotations()->create(['status' => Status::Closed, 'name' => 'Antiga']);

        Livewire::test(Quotations::class)->callTableAction('reopen', $reopened);

        $this->assertSame($reopened->id, $user->openQuotation()->id);
        $this->assertNotSame($stillOpen->id, $user->openQuotation()->id);
    }

    public function test_clicking_a_quotation_links_to_its_dedicated_page(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Closed]);

        $url = Livewire::test(Quotations::class)->instance()->getTable()->getRecordUrl($quotation);

        $this->assertSame(ViewQuotation::getUrl(['quotation' => $quotation->id]), $url);
    }
}
