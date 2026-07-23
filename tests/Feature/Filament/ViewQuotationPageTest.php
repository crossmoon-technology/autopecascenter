<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\ViewQuotation;
use App\Models\Quotation\Enums\Status;
use App\Models\QuotationItem\Enums\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewQuotationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_does_not_appear_in_navigation(): void
    {
        $this->assertFalse(ViewQuotation::shouldRegisterNavigation());
    }

    public function test_shows_the_items_in_that_quotation(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->assertSuccessful()
            ->assertSee('HF-21');
    }

    public function test_returns_404_for_a_quotation_that_belongs_to_another_user(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        $quotation = $otherUser->quotations()->create(['status' => Status::Open]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->get(ViewQuotation::getUrl(['quotation' => $quotation->id], panel: 'super-admin'))
            ->assertNotFound();
    }

    public function test_shows_an_empty_state_when_the_quotation_has_no_items(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->assertSuccessful()
            ->assertSee('Nenhuma peça nessa cotação ainda');
    }

    public function test_remove_action_deletes_the_item(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $item = $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('remove', $item)
            ->assertNotified();

        $this->assertDatabaseMissing('quotation_items', ['id' => $item->id]);
    }

    public function test_edit_note_action_saves_a_note(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $item = $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('editNote', $item, data: ['quantity' => 1, 'note' => 'Cliente pediu urgência'])
            ->assertNotified();

        $this->assertDatabaseHas('quotation_items', [
            'id' => $item->id,
            'note' => 'Cliente pediu urgência',
        ]);
    }

    public function test_edit_note_action_prefills_the_existing_note_and_quantity(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $item = $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21', 'quantity' => 3, 'note' => 'Nota antiga']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->mountTableAction('editNote', $item)
            ->assertTableActionDataSet(['quantity' => 3, 'note' => 'Nota antiga']);
    }

    public function test_edit_action_updates_the_quantity(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $item = $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21', 'quantity' => 1]);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('editNote', $item, data: ['quantity' => 5, 'note' => null])
            ->assertNotified();

        $this->assertSame(5, $item->fresh()->quantity);
    }

    public function test_shows_the_quantity_column(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21', 'quantity' => 7]);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->assertSuccessful()
            ->assertSee('7');
    }

    public function test_save_action_closes_the_quotation_with_the_given_name(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('save', data: ['name' => 'Orçamento Cliente X'])
            ->assertNotified();

        $quotation->refresh();
        $this->assertSame(Status::Closed, $quotation->status);
        $this->assertSame('Orçamento Cliente X', $quotation->name);
    }

    public function test_save_action_falls_back_to_a_default_name_when_left_blank(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('save', data: ['name' => '']);

        $this->assertSame("Cotação #{$quotation->id}", $quotation->fresh()->name);
    }

    public function test_save_action_is_hidden_once_the_quotation_is_closed(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Closed, 'name' => 'Fechada']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->assertTableActionHidden('save')
            ->assertTableActionVisible('reopen');
    }

    public function test_reopen_action_sets_the_quotation_back_to_open(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Closed, 'name' => 'Fechada']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('reopen')
            ->assertNotified()
            ->assertTableActionHidden('reopen')
            ->assertTableActionVisible('save');

        $this->assertSame(Status::Open, $quotation->fresh()->status);
    }

    public function test_export_actions_are_hidden_when_the_quotation_has_no_items(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->assertTableActionHidden('exportPdf')
            ->assertTableActionHidden('exportCsv')
            ->assertTableActionHidden('exportXlsx')
            ->assertTableActionHidden('exportJson');
    }

    public function test_export_pdf_downloads_a_pdf(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('exportPdf')
            ->assertSuccessful();
    }

    public function test_export_csv_downloads_a_csv(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('exportCsv')
            ->assertSuccessful();
    }

    public function test_export_xlsx_downloads_an_xlsx(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('exportXlsx')
            ->assertSuccessful();
    }

    public function test_export_json_downloads_a_json(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(ViewQuotation::class, ['quotation' => $quotation->id])
            ->callTableAction('exportJson')
            ->assertSuccessful();
    }
}
