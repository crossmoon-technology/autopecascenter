<?php

namespace Tests\Feature\Livewire;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Quotations;
use App\Livewire\QuotationCart;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\Quotation\Enums\Status;
use App\Models\QuotationItem\Enums\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuotationCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_an_empty_state_when_there_is_no_open_quotation(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(QuotationCart::class)
            ->assertSuccessful()
            ->assertSee('Sua cotação está vazia');
    }

    public function test_shows_the_items_in_the_open_quotation(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['manufacturer_id' => $manufacturer->id, 'source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(QuotationCart::class)
            ->assertSuccessful()
            ->assertSee('HF-21')
            ->assertSee('Cofap');
    }

    public function test_does_not_show_another_users_open_quotation(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherQuotation = $otherUser->quotations()->create(['status' => Status::Open]);
        $otherQuotation->items()->create(['source' => Source::Iframe, 'codigo' => 'SEGREDO-123']);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(QuotationCart::class)
            ->assertDontSee('SEGREDO-123');
    }

    public function test_does_not_show_a_closed_quotation(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Closed, 'name' => 'Fechada']);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(QuotationCart::class)
            ->assertDontSee('HF-21');
    }

    public function test_remove_item_deletes_it_and_notifies_the_widget_to_refresh(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $item = $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(QuotationCart::class)
            ->call('removeItem', $item->id)
            ->assertDispatched('quotation-updated');

        $this->assertDatabaseMissing('quotation_items', ['id' => $item->id]);
    }

    public function test_save_closes_the_quotation_with_the_given_name(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(QuotationCart::class)
            ->set('name', 'Orçamento Cliente X')
            ->call('save')
            ->assertNotified()
            ->assertDispatched('quotation-updated');

        $quotation->refresh();
        $this->assertSame(Status::Closed, $quotation->status);
        $this->assertSame('Orçamento Cliente X', $quotation->name);
    }

    public function test_save_falls_back_to_a_default_name_when_left_blank(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(QuotationCart::class)->call('save');

        $this->assertSame("Cotação #{$quotation->id}", $quotation->fresh()->name);
    }

    public function test_save_warns_instead_of_saving_an_empty_quotation(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);

        Livewire::test(QuotationCart::class)
            ->call('save')
            ->assertNotified('Adicione ao menos uma peça antes de salvar.');

        $this->assertSame(Status::Open, $quotation->fresh()->status);
    }

    public function test_a_saved_quotation_becomes_visible_on_the_quotations_page_after_saving(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $quotation = $user->quotations()->create(['status' => Status::Open]);
        $quotation->items()->create(['manufacturer_id' => $manufacturer->id, 'source' => Source::Iframe, 'codigo' => 'HF-21']);

        Livewire::test(QuotationCart::class)
            ->set('name', 'Orçamento Cliente X')
            ->call('save');

        Livewire::test(Quotations::class)
            ->assertSee('Orçamento Cliente X');
    }
}
