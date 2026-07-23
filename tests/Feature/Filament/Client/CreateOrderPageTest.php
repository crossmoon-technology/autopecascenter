<?php

namespace Tests\Feature\Filament\Client;

use App\Enums\Role;
use App\Filament\Client\Pages\CreateOrder;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateOrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_label_is_criar_pedido(): void
    {
        $this->assertSame('Criar pedido', CreateOrder::getNavigationLabel());
    }

    public function test_shows_the_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(CreateOrder::class)
            ->assertSuccessful()
            ->assertSee('Adicionar peça');
    }

    public function test_fields_carry_the_onboarding_tutorial_target_classes(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        Livewire::test(CreateOrder::class)
            ->assertSuccessful()
            ->assertSeeHtml('onboarding-target-description')
            ->assertSeeHtml('onboarding-target-quantity')
            ->assertSeeHtml('onboarding-target-manufacturers');
    }

    public function test_submitting_creates_the_order_with_items_quantities_and_manufacturer_preferences(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($user);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap']);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Pastilha de freio dianteira', 'quantity' => 4, 'manufacturer_ids' => [$manufacturer->id]],
                    ['description' => 'Amortecedor traseiro', 'quantity' => 2, 'manufacturer_ids' => []],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(2, $order->items()->count());

        $itemWithPreference = $order->items()->where('description', 'Pastilha de freio dianteira')->firstOrFail();
        $this->assertSame(4, $itemWithPreference->quantity);
        $this->assertTrue($itemWithPreference->preferredManufacturers->contains($manufacturer));

        $itemWithoutPreference = $order->items()->where('description', 'Amortecedor traseiro')->firstOrFail();
        $this->assertSame(2, $itemWithoutPreference->quantity);
        $this->assertTrue($itemWithoutPreference->preferredManufacturers->isEmpty());
    }

    public function test_submitting_dispatches_order_created_with_the_new_orders_id(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($user);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Vela de ignição', 'quantity' => 1, 'manufacturer_ids' => []],
                ],
            ])
            ->call('save')
            ->assertDispatched('order-created', function (string $name, array $params) use ($user): bool {
                return $params['orderId'] === Order::query()->where('user_id', $user->id)->sole()->id;
            });
    }

    public function test_manufacturer_preference_options_are_scoped_to_the_inviting_sellers_preferences(): void
    {
        $enabled = Manufacturer::factory()->create(['name' => 'Cofap']);
        $notEnabled = Manufacturer::factory()->create(['name' => 'Magneti Marelli']);

        $seller = User::factory()->create(['role' => Role::Admin]);
        $seller->preferredManufacturers()->attach($enabled);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        $this->actingAs($client);

        Livewire::test(CreateOrder::class)
            ->assertSee('Cofap')
            ->assertDontSee('Magneti Marelli');
    }

    public function test_preserves_the_chosen_manufacturer_preference_order(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($user);
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap']);
        $marelli = Manufacturer::factory()->create(['name' => 'Magneti Marelli']);
        $hipper = Manufacturer::factory()->create(['name' => 'Hipper Freios']);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Pastilha de freio', 'quantity' => 1, 'manufacturer_ids' => [$marelli->id, $hipper->id, $cofap->id]],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $item = Order::query()->where('user_id', $user->id)->firstOrFail()->items()->firstOrFail();

        $this->assertSame(
            [$marelli->id, $hipper->id, $cofap->id],
            $item->preferredManufacturers()->get()->pluck('id')->all()
        );
    }

    public function test_submitting_saves_the_order_level_comment(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($user);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Vela de ignição', 'quantity' => 1, 'manufacturer_ids' => []],
                ],
                'notes' => 'Entregar no período da tarde, por favor.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Entregar no período da tarde, por favor.', $order->notes);
    }

    public function test_leaves_notes_null_when_left_blank(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($user);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Vela de ignição', 'quantity' => 1, 'manufacturer_ids' => []],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNull($order->notes);
    }

    public function test_allows_submitting_more_than_one_order(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        Order::factory()->for($user)->create();
        $this->actingAs($user);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Vela de ignição', 'quantity' => 1, 'manufacturer_ids' => []],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_resets_the_form_after_submitting_so_another_order_can_be_started(): void
    {
        $user = User::factory()->create(['role' => Role::Client]);
        $this->actingAs($user);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'items' => [
                    ['description' => 'Vela de ignição', 'quantity' => 1, 'manufacturer_ids' => []],
                ],
            ])
            ->call('save')
            ->assertSet('data.items', function (array $items): bool {
                return count($items) === 1
                    && array_values($items)[0]['description'] === ''
                    && (int) array_values($items)[0]['quantity'] === 1;
            });
    }
}
