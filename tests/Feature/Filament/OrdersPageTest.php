<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Orders;
use App\Filament\Pages\Buscas\ViewQuotation;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use App\Models\Quotation\Enums\Status as QuotationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_vendas_navigation_group(): void
    {
        $this->assertSame('Vendas', Orders::getNavigationGroup());
    }

    public function test_navigation_label_is_pedidos(): void
    {
        $this->assertSame('Pedidos', Orders::getNavigationLabel());
    }

    public function test_shows_an_empty_state_when_there_are_no_orders(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertSee('Nenhum pedido recebido ainda');
    }

    public function test_shows_orders_from_invited_clients_with_notes(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id, 'name' => 'João da Oficina']);
        $order = Order::factory()->for($client)->create(['notes' => 'Entregar de manhã.']);
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 3]);

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertSee('João da Oficina')
            ->assertSee('Entregar de manhã.');
    }

    public function test_new_orders_default_to_pending(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);

        $order = Order::factory()->for($client)->create();

        $this->assertSame(Status::Pending, $order->status);
    }

    public function test_shows_orders_of_every_status_not_just_pending(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $pending = Order::factory()->for($client)->create(['notes' => 'Pedido pendente']);
        $pending->items()->create(['description' => 'Item', 'quantity' => 1]);
        $processing = Order::factory()->for($client)->processing()->create(['notes' => 'Pedido processando']);
        $processing->items()->create(['description' => 'Item', 'quantity' => 1]);
        $finished = Order::factory()->for($client)->finished()->create(['notes' => 'Pedido finalizado']);
        $finished->items()->create(['description' => 'Item', 'quantity' => 1]);

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertSee('Pedido pendente')
            ->assertSee('Pedido processando')
            ->assertSee('Pedido finalizado');
    }

    public function test_does_not_show_orders_from_another_sellers_clients(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherClient = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $otherSeller->id, 'name' => 'Cliente de outro vendedor']);
        $order = Order::factory()->for($otherClient)->create();
        $order->items()->create(['description' => 'Peça de outro vendedor', 'quantity' => 1]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertDontSee('Cliente de outro vendedor');
    }

    public function test_shows_guided_quotation_button_only_for_pending_or_processing_orders(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);

        $pending = Order::factory()->for($client)->create();
        $pending->items()->create(['description' => 'Item pendente', 'quantity' => 1]);

        Livewire::test(Orders::class)
            ->assertTableActionVisible('startGuidedQuotation', $pending);

        $quotation = $seller->quotations()->create(['status' => QuotationStatus::Closed]);
        $finished = Order::factory()->for($client)->finished()->create(['quotation_id' => $quotation->id]);
        $finished->items()->create(['description' => 'Item finalizado', 'quantity' => 1]);

        Livewire::test(Orders::class)
            ->assertTableActionHidden('startGuidedQuotation', $finished);
    }

    public function test_status_column_updates_the_order_when_changed(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        $component = Livewire::test(Orders::class);
        $recordKey = $component->instance()->getTableRecordKey($order);
        $component->call('updateTableColumnState', 'status', $recordKey, Status::Processing->value);

        $this->assertSame(Status::Processing, $order->fresh()->status);
    }

    public function test_can_set_status_to_cancelled_without_a_quotation(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        $component = Livewire::test(Orders::class);
        $recordKey = $component->instance()->getTableRecordKey($order);
        $component->call('updateTableColumnState', 'status', $recordKey, Status::Cancelled->value);

        $this->assertSame(Status::Cancelled, $order->fresh()->status);
    }

    public function test_rejects_marking_an_order_as_finished_without_an_associated_quotation(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        $component = Livewire::test(Orders::class);
        $recordKey = $component->instance()->getTableRecordKey($order);
        $component
            ->call('updateTableColumnState', 'status', $recordKey, Status::Finished->value)
            ->assertNotified();

        $this->assertSame(Status::Pending, $order->fresh()->status);
    }

    public function test_attach_quotation_button_shows_anexar_when_none_linked_and_trocar_once_linked(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertSee('Anexar cotação');

        $quotation = $seller->quotations()->create(['status' => QuotationStatus::Closed]);
        $order->update(['quotation_id' => $quotation->id]);

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertSee('Trocar cotação');
    }

    public function test_quotation_column_links_to_the_quotations_page_when_associated(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);
        $quotation = $seller->quotations()->create(['status' => QuotationStatus::Closed, 'name' => 'Cotação do João']);
        $order->update(['quotation_id' => $quotation->id]);

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertSeeHtml('href="'.ViewQuotation::getUrl(['quotation' => $quotation->id]).'"');
    }

    public function test_quotation_column_is_not_a_link_when_no_quotation_is_associated(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        Livewire::test(Orders::class)
            ->assertSuccessful()
            ->assertSee('Nenhuma');
    }

    public function test_allows_marking_an_order_as_finished_once_a_quotation_is_associated(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $order = Order::factory()->for($client)->create();
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);
        $quotation = $seller->quotations()->create(['status' => QuotationStatus::Closed, 'name' => 'Cotação do João']);

        $component = Livewire::test(Orders::class);
        $component->callTableAction('attachQuotation', $order, data: ['quotation_id' => $quotation->id]);

        $recordKey = $component->instance()->getTableRecordKey($order);
        $component->call('updateTableColumnState', 'status', $recordKey, Status::Finished->value);

        $order->refresh();
        $this->assertSame(Status::Finished, $order->status);
        $this->assertSame($quotation->id, $order->quotation_id);
    }

    public function test_quotation_options_are_scoped_to_the_sellers_own_closed_quotations(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $ownClosed = $seller->quotations()->create(['status' => QuotationStatus::Closed, 'name' => 'Orcamento Proprio']);

        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherSeller->quotations()->create(['status' => QuotationStatus::Closed, 'name' => 'Orcamento Alheio']);
        $seller->quotations()->create(['status' => QuotationStatus::Open, 'name' => 'Ainda Aberta']);

        $this->actingAs($seller);

        $page = new Orders;
        $method = new \ReflectionMethod($page, 'quotationOptions');
        $method->setAccessible(true);

        $this->assertSame([$ownClosed->id => 'Orcamento Proprio'], $method->invoke($page));
    }

    public function test_create_order_action_creates_an_order_for_the_selected_client(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);

        Livewire::test(Orders::class)
            ->callTableAction('createOrder', data: [
                'user_id' => $client->id,
                'notes' => 'Cliente ligou pedindo.',
                'items' => [
                    ['description' => 'Pastilha de freio', 'quantity' => 2, 'manufacturer_ids' => [$manufacturer->id]],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $order = Order::query()->where('user_id', $client->id)->sole();
        $this->assertSame('Cliente ligou pedindo.', $order->notes);
        $this->assertSame(Status::Pending, $order->status);

        $item = $order->items->sole();
        $this->assertSame('Pastilha de freio', $item->description);
        $this->assertSame(2, $item->quantity);
        $this->assertSame([$manufacturer->id], $item->preferredManufacturers->pluck('id')->all());
    }

    public function test_create_order_action_client_options_are_scoped_to_this_sellers_invited_clients(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $ownClient = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id, 'name' => 'Cliente Proprio']);

        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        User::factory()->create(['role' => Role::Client, 'invited_by_id' => $otherSeller->id, 'name' => 'Cliente Alheio']);

        $this->actingAs($seller);

        $page = new Orders;
        $method = new \ReflectionMethod($page, 'clientOptionsForNewOrder');
        $method->setAccessible(true);

        $this->assertSame([$ownClient->id => 'Cliente Proprio'], $method->invoke($page));
    }

    public function test_create_order_action_refuses_a_client_not_invited_by_this_seller(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherClient = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $otherSeller->id]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        try {
            Livewire::test(Orders::class)->callTableAction('createOrder', data: [
                'user_id' => $otherClient->id,
                'notes' => null,
                'items' => [
                    ['description' => 'Peça', 'quantity' => 1, 'manufacturer_ids' => []],
                ],
            ]);
        } catch (ModelNotFoundException) {
            // Esperado — o findOrFail dentro da action rejeita um user_id fora dos
            // clientes convidados por este vendedor. O importante é que nenhum pedido
            // chegue a ser criado, verificado abaixo independente de a exceção
            // propagar visivelmente aqui ou ser absorvida pelo pipeline da Action.
        }

        $this->assertSame(0, Order::query()->where('user_id', $otherClient->id)->count());
    }

    public function test_clearing_the_quotation_reverts_a_finished_order_back_to_pending(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $quotation = $seller->quotations()->create(['status' => QuotationStatus::Closed]);
        $order = Order::factory()->for($client)->finished()->create(['quotation_id' => $quotation->id]);
        $order->items()->create(['description' => 'Pastilha de freio', 'quantity' => 1]);

        Livewire::test(Orders::class)
            ->callTableAction('attachQuotation', $order, data: ['quotation_id' => null]);

        $order->refresh();
        $this->assertNull($order->quotation_id);
        $this->assertSame(Status::Pending, $order->status);
    }
}
