<?php

namespace Tests\Feature\Livewire;

use App\Enums\Role;
use App\Livewire\PendingOrdersIndicator;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PendingOrdersIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_no_badge_when_there_are_no_pending_orders(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PendingOrdersIndicator::class)
            ->assertSuccessful()
            ->assertDontSeeHtml('class="poi-badge"');
    }

    public function test_counts_pending_and_processing_orders_from_invited_clients(): void
    {
        $seller = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($seller);

        $client = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $seller->id]);
        Order::factory()->for($client)->create();
        Order::factory()->for($client)->processing()->create();
        Order::factory()->for($client)->finished()->create();

        Livewire::test(PendingOrdersIndicator::class)
            ->assertSuccessful()
            ->assertSeeHtml('>2<');
    }

    public function test_does_not_count_another_sellers_clients_orders(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherClient = User::factory()->create(['role' => Role::Client, 'invited_by_id' => $otherSeller->id]);
        Order::factory()->for($otherClient)->create();

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PendingOrdersIndicator::class)
            ->assertSuccessful()
            ->assertDontSeeHtml('class="poi-badge"');
    }
}
