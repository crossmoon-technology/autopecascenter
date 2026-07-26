<?php

namespace Tests\Feature\Filament\Widgets;

use App\Enums\Role;
use App\Filament\Widgets\OrdersOverview;
use App\Models\Order;
use App\Models\Order\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_heading_is_pedidos(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->assertSame('Pedidos', Livewire::test(OrdersOverview::class)->instance()->getHeading());
    }

    public function test_defaults_to_the_last_7_days_filter(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(OrdersOverview::class)->assertSet('filter', 'last_7_days');
    }

    public function test_shows_zero_counts_when_there_are_no_orders(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(OrdersOverview::class)->assertSuccessful();

        $this->assertSame(0, Livewire::test(OrdersOverview::class)->instance()->totalCount());
    }

    public function test_counts_todays_orders_by_status_by_default(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();

        Order::factory()->for($client)->for($seller, 'seller')->create();
        Order::factory()->for($client)->for($seller, 'seller')->processing()->create();
        Order::factory()->for($client)->for($seller, 'seller')->finished()->create();
        Order::factory()->for($client)->for($seller, 'seller')->cancelled()->create();

        $counts = Livewire::test(OrdersOverview::class)->instance()->statusCounts();

        $this->assertSame(1, $counts[Status::Pending->value]);
        $this->assertSame(1, $counts[Status::Processing->value]);
        $this->assertSame(1, $counts[Status::Finished->value]);
        $this->assertSame(1, $counts[Status::Cancelled->value]);
    }

    public function test_chart_data_matches_the_status_counts(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();

        Order::factory()->for($client)->for($seller, 'seller')->create();
        Order::factory()->for($client)->for($seller, 'seller')->create();
        Order::factory()->for($client)->for($seller, 'seller')->processing()->create();

        $method = new \ReflectionMethod(OrdersOverview::class, 'getData');
        $method->setAccessible(true);

        $component = Livewire::test(OrdersOverview::class);
        $data = $method->invoke($component->instance());

        $this->assertSame(['Pendente', 'Processando', 'Finalizado', 'Cancelado'], $data['labels']);
        $this->assertSame([2, 1, 0, 0], $data['datasets'][0]['data']);
    }

    public function test_does_not_count_orders_outside_the_selected_period(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();

        Order::factory()->for($client)->for($seller, 'seller')->create();
        $lastMonth = Order::factory()->for($client)->for($seller, 'seller')->create();
        $lastMonth->timestamps = false;
        $lastMonth->created_at = now()->subMonth();
        $lastMonth->save();

        $component = Livewire::test(OrdersOverview::class);

        $this->assertSame(1, $component->instance()->totalCount());
    }

    public function test_all_period_includes_orders_from_far_in_the_past(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();

        $order = Order::factory()->for($client)->for($seller, 'seller')->create();
        $order->timestamps = false;
        $order->created_at = now()->subYears(2);
        $order->save();

        $componentDay = Livewire::test(OrdersOverview::class)->set('filter', 'day');
        $this->assertSame(0, $componentDay->instance()->totalCount());

        $componentAll = Livewire::test(OrdersOverview::class)->set('filter', 'all');
        $this->assertSame(1, $componentAll->instance()->totalCount());
    }

    public function test_last_31_days_period_excludes_older_orders_but_includes_recent_ones(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();

        $recent = Order::factory()->for($client)->for($seller, 'seller')->create();
        $recent->timestamps = false;
        $recent->created_at = now()->subDays(10);
        $recent->save();

        $old = Order::factory()->for($client)->for($seller, 'seller')->create();
        $old->timestamps = false;
        $old->created_at = now()->subDays(40);
        $old->save();

        $component7Days = Livewire::test(OrdersOverview::class)->set('filter', 'last_7_days');
        $this->assertSame(0, $component7Days->instance()->totalCount());

        $component31Days = Livewire::test(OrdersOverview::class)->set('filter', 'last_31_days');
        $this->assertSame(1, $component31Days->instance()->totalCount());
    }

    public function test_does_not_count_orders_from_another_sellers_clients(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherClient = User::factory()->clientOf($otherSeller)->create();
        Order::factory()->for($otherClient)->create();

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->assertSame(0, Livewire::test(OrdersOverview::class)->instance()->totalCount());
    }

    public function test_falls_back_to_last_7_days_when_filter_value_is_invalid(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);
        $client = User::factory()->clientOf($seller)->create();
        Order::factory()->for($client)->for($seller, 'seller')->create();

        $component = Livewire::test(OrdersOverview::class)->set('filter', 'not-a-real-period');

        $this->assertSame(1, $component->instance()->totalCount());
    }
}
