<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Configuracoes\NoResultSearches;
use App\Models\SearchHistory;
use App\Models\SearchHistory\Enums\Method;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NoResultSearchesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_configuracoes_navigation_group(): void
    {
        $this->assertSame('Configurações', NoResultSearches::getNavigationGroup());
    }

    public function test_lists_queries_that_found_nothing(): void
    {
        SearchHistory::factory()->create([
            'query' => 'ABC-999',
            'method' => Method::Database,
            'found_results' => false,
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(NoResultSearches::class)
            ->assertSuccessful()
            ->assertSee('ABC-999');
    }

    public function test_hides_queries_that_found_something(): void
    {
        SearchHistory::factory()->create([
            'query' => '16002',
            'method' => Method::Database,
            'found_results' => true,
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(NoResultSearches::class)
            ->assertSuccessful()
            ->assertDontSee('16002');
    }

    public function test_hides_api_searches_since_they_cannot_be_measured_reliably(): void
    {
        SearchHistory::factory()->create([
            'query' => 'HF-21',
            'method' => Method::Api,
            'found_results' => null,
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(NoResultSearches::class)
            ->assertSuccessful()
            ->assertDontSee('HF-21');
    }

    public function test_aggregates_the_same_query_from_multiple_users_into_one_row_with_a_count(): void
    {
        $userA = User::factory()->create(['role' => Role::SuperAdmin]);
        $userB = User::factory()->create(['role' => Role::SuperAdmin]);

        SearchHistory::factory()->create(['user_id' => $userA->id, 'query' => 'XYZ-1', 'method' => Method::Database, 'found_results' => false]);
        SearchHistory::factory()->create(['user_id' => $userB->id, 'query' => 'XYZ-1', 'method' => Method::Database, 'found_results' => false]);
        SearchHistory::factory()->create(['user_id' => $userA->id, 'query' => 'XYZ-1', 'method' => Method::Database, 'found_results' => false]);

        $this->actingAs($userA);

        Livewire::test(NoResultSearches::class)
            ->assertSuccessful()
            ->assertSee('XYZ-1')
            ->assertSee('3');
    }

    public function test_shows_an_empty_state_when_nothing_is_missing(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(NoResultSearches::class)
            ->assertSuccessful()
            ->assertSee('Nenhuma busca sem resultado até agora');
    }
}
