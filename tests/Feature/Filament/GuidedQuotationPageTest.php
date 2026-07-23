<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\GuidedQuotation;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Order\Enums\Status as OrderStatus;
use App\Models\Part;
use App\Models\Quotation\Enums\Status as QuotationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuidedQuotationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefills_the_search_with_the_first_items_description_and_searches_automatically(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HG 41297', 'conversoes' => []]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 2]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->assertSuccessful()
            ->assertSet('data.codigo', 'HG 41297')
            ->assertSee('Item 1 de 1')
            ->assertSee('HG 41297');
    }

    public function test_preselects_the_items_preferred_manufacturers(): void
    {
        $preferred = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $preferred->getKey(), 'is_active' => true]);
        $notPreferred = Manufacturer::factory()->create(['name' => 'Sabo', 'is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $notPreferred->getKey(), 'is_active' => true]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $item = $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);
        $item->preferredManufacturers()->attach($preferred, ['position' => 0]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->assertSet("data.manufacturers.{$preferred->id}", true)
            ->assertSet("data.manufacturers.{$notPreferred->id}", false);
    }

    public function test_add_and_continue_adds_the_part_to_the_open_quotation_and_advances(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HG 41297', 'conversoes' => []]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 3]);
        $order->items()->create(['description' => 'Outra peça', 'quantity' => 1]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('addAndContinue', $part->id)
            ->assertSet('currentItemIndex', 1)
            ->assertSee('Item 2 de 2');

        $this->assertDatabaseHas('quotation_items', [
            'part_id' => $part->id,
            'manufacturer_id' => $manufacturer->id,
            'quantity' => 3,
        ]);
        $this->assertSame($part->id, $seller->openQuotation()->items()->first()->part_id);
    }

    public function test_add_and_continue_accumulates_quantity_when_the_same_part_is_added_twice(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HG 41297', 'conversoes' => []]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 2]);
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 3]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('addAndContinue', $part->id)
            ->call('addAndContinue', $part->id);

        $this->assertSame(1, $seller->openQuotation()->items()->count());
        $this->assertSame(5, $seller->openQuotation()->items()->first()->quantity);
    }

    public function test_skip_item_advances_without_adding_anything(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Item 1', 'quantity' => 1]);
        $order->items()->create(['description' => 'Item 2', 'quantity' => 1]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('skipItem')
            ->assertSet('currentItemIndex', 1)
            ->assertSee('Item 2 de 2');

        $this->assertDatabaseCount('quotation_items', 0);
    }

    public function test_shows_completion_state_after_the_last_item(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Único item', 'quantity' => 1]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('skipItem')
            ->assertSet('currentItemIndex', 1)
            ->assertSee('Revisão da cotação')
            ->assertSee('Nenhuma peça foi adicionada durante essa cotação guiada.');
    }

    public function test_completion_screen_shows_added_items_with_quantity_and_a_save_button(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HG 41297', 'conversoes' => []]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 3]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('addAndContinue', $part->id)
            ->assertSee('3x HG 41297')
            ->assertSee('Cofap')
            ->assertSee('Salvar cotação');
    }

    public function test_remove_review_item_removes_it_from_the_open_quotation(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HG 41297', 'conversoes' => []]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 1]);

        $component = Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('addAndContinue', $part->id);

        $itemId = $seller->openQuotation()->items()->first()->id;

        $component->call('removeReviewItem', $itemId);

        $this->assertDatabaseCount('quotation_items', 0);
    }

    public function test_save_quotation_closes_it_and_associates_it_with_the_order(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HG 41297', 'conversoes' => []]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 1]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('addAndContinue', $part->id)
            ->set('quotationName', 'Cotação do João')
            ->set('reviewConfirmed', true)
            ->call('saveQuotation')
            ->assertNotified();

        $quotation = $seller->openQuotationOrNull();
        $this->assertNull($quotation);

        $savedQuotation = $seller->quotations()->where('name', 'Cotação do João')->firstOrFail();
        $this->assertSame(QuotationStatus::Closed, $savedQuotation->status);

        $this->assertSame($savedQuotation->id, $order->fresh()->quotation_id);
    }

    public function test_save_quotation_requires_at_least_one_item(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Único item', 'quantity' => 1]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('skipItem')
            ->call('saveQuotation')
            ->assertNotified();

        $this->assertNull($order->fresh()->quotation_id);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_save_quotation_requires_the_review_checkbox_to_be_confirmed(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HG 41297', 'conversoes' => []]);

        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'HG 41297', 'quantity' => 1]);

        Livewire::test(GuidedQuotation::class, ['order' => $order->id])
            ->call('addAndContinue', $part->id)
            ->assertSet('reviewConfirmed', false)
            ->call('saveQuotation')
            ->assertNotified();

        $this->assertNull($order->fresh()->quotation_id);
        $this->assertNotNull($seller->openQuotationOrNull());
    }

    public function test_returns_404_for_an_order_belonging_to_another_sellers_client(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherClient = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $otherSeller->id]);
        $order = Order::factory()->for($otherClient)->create();
        $order->items()->create(['description' => 'Peça', 'quantity' => 1]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->get(GuidedQuotation::getUrl(['order' => $order->id], panel: 'super-admin'))
            ->assertNotFound();
    }
}
