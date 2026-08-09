<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Filament\Pages\Buscas\History;
use App\Models\SearchHistory;
use App\Models\SearchHistory\Enums\Method;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_buscas_navigation_group(): void
    {
        $this->assertSame('Buscas', History::getNavigationGroup());
    }

    public function test_lists_the_signed_in_users_search_history(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        SearchHistory::factory()->create([
            'user_id' => $user->id,
            'query' => '16002',
            'method' => Method::Database,
        ]);
        $this->actingAs($user);

        Livewire::test(History::class)
            ->assertSuccessful()
            ->assertSee('16002')
            ->assertSee('Base de dados');
    }

    public function test_does_not_show_another_users_search_history(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        SearchHistory::factory()->create(['user_id' => $otherUser->id, 'query' => 'SEGREDO-123']);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(History::class)
            ->assertSuccessful()
            ->assertDontSee('SEGREDO-123');
    }

    public function test_shows_an_empty_state_when_nothing_was_searched_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(History::class)
            ->assertSuccessful()
            ->assertSee('Nenhuma busca registrada ainda');
    }

    /**
     * Method::Api é o método legado da extinta aba "API" (removida do painel) —
     * linhas antigas continuam existindo e devem seguir exibindo o badge normalmente.
     */
    public function test_shows_the_legacy_api_method_badge(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        SearchHistory::factory()->create([
            'user_id' => $user->id,
            'query' => 'HF-21',
            'method' => Method::Api,
        ]);
        $this->actingAs($user);

        Livewire::test(History::class)
            ->assertSuccessful()
            ->assertSee('HF-21')
            ->assertSee('API');
    }

    public function test_clicking_a_database_search_row_links_to_the_database_page_prefilled(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $record = SearchHistory::factory()->create([
            'user_id' => $user->id,
            'query' => '16002',
            'method' => Method::Database,
        ]);
        $this->actingAs($user);

        $url = Livewire::test(History::class)->instance()->getTable()->getRecordUrl($record);

        $this->assertSame(CatalogDatabaseSearch::getUrl(['codigo' => '16002']), $url);
    }

    /**
     * Method::Api é o método legado da extinta aba "API" — essa busca vive só
     * na Base de dados agora, então linhas antigas caem lá (mesmo tratamento
     * dado a Method::Equivalence, o outro método extinto).
     */
    public function test_clicking_a_legacy_api_search_row_links_to_the_database_page_prefilled(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $record = SearchHistory::factory()->create([
            'user_id' => $user->id,
            'query' => 'HF-21',
            'method' => Method::Api,
        ]);
        $this->actingAs($user);

        $url = Livewire::test(History::class)->instance()->getTable()->getRecordUrl($record);

        $this->assertSame(CatalogDatabaseSearch::getUrl(['codigo' => 'HF-21']), $url);
    }

    public function test_clicking_a_legacy_equivalence_search_row_links_to_the_database_page_prefilled(): void
    {
        // Method::Equivalence é o método legado do extinto "Achar código original" —
        // essa busca vive só na Base de dados agora, então linhas antigas caem lá.
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $record = SearchHistory::factory()->create([
            'user_id' => $user->id,
            'query' => 'MG 19038',
            'method' => Method::Equivalence,
        ]);
        $this->actingAs($user);

        $url = Livewire::test(History::class)->instance()->getTable()->getRecordUrl($record);

        $this->assertSame(CatalogDatabaseSearch::getUrl(['codigo' => 'MG 19038']), $url);
    }

    public function test_only_shows_the_100_most_recent_searches(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        foreach (range(1, 105) as $i) {
            $record = SearchHistory::factory()->create([
                'user_id' => $user->id,
                'query' => "codigo-{$i}",
            ]);
            $record->timestamps = false;
            $record->created_at = now()->subMinutes(200 - $i);
            $record->save();
        }

        $records = Livewire::test(History::class)->instance()->getTable()->getRecords();

        $this->assertSame(100, $records->total());
        $this->assertTrue($records->contains('query', 'codigo-105'));
        $this->assertFalse($records->contains('query', 'codigo-1'));
    }
}
