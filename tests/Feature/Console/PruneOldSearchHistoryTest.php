<?php

namespace Tests\Feature\Console;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneOldSearchHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_search_history_older_than_a_month(): void
    {
        $user = User::factory()->create();

        $old = SearchHistory::factory()->create(['user_id' => $user->id]);
        $old->timestamps = false;
        $old->created_at = now()->subMonth()->subDay();
        $old->save();

        $recent = SearchHistory::factory()->create(['user_id' => $user->id]);
        $recent->timestamps = false;
        $recent->created_at = now()->subDays(2);
        $recent->save();

        $this->artisan('search-history:prune')->assertSuccessful();

        $this->assertDatabaseMissing('search_histories', ['id' => $old->id]);
        $this->assertDatabaseHas('search_histories', ['id' => $recent->id]);
    }

    public function test_keeps_search_history_from_exactly_one_month_ago(): void
    {
        $user = User::factory()->create();

        $record = SearchHistory::factory()->create(['user_id' => $user->id]);
        $record->timestamps = false;
        $record->created_at = now()->subMonth()->addMinute();
        $record->save();

        $this->artisan('search-history:prune')->assertSuccessful();

        $this->assertDatabaseHas('search_histories', ['id' => $record->id]);
    }

    public function test_prunes_across_all_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        foreach ([$userA, $userB] as $user) {
            $record = SearchHistory::factory()->create(['user_id' => $user->id]);
            $record->timestamps = false;
            $record->created_at = now()->subMonths(2);
            $record->save();
        }

        $this->artisan('search-history:prune')->assertSuccessful();

        $this->assertSame(0, SearchHistory::query()->count());
    }
}
