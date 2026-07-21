<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Favorites;
use App\Filament\Pages\Buscas\ViewFavoriteList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FavoritesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lives_under_the_buscas_navigation_group(): void
    {
        $this->assertSame('Buscas', Favorites::getNavigationGroup());
    }

    public function test_every_user_gets_a_default_list_that_always_appears(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Favorites::class)
            ->assertSuccessful()
            ->assertSee('Lista padrão');
    }

    public function test_does_not_show_another_users_lists(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        $otherUser->favoriteLists()->create(['name' => 'Lista secreta']);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Favorites::class)
            ->assertSuccessful()
            ->assertDontSee('Lista secreta');
    }

    public function test_create_action_adds_a_new_list(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(Favorites::class)
            ->callTableAction('create', data: ['name' => 'Orçamento Cliente X'])
            ->assertNotified();

        $this->assertDatabaseHas('favorite_lists', [
            'user_id' => $user->id,
            'name' => 'Orçamento Cliente X',
            'is_default' => false,
        ]);
    }

    public function test_rename_action_renames_a_list(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Nome antigo']);

        Livewire::test(Favorites::class)
            ->callTableAction('rename', $list, data: ['name' => 'Nome novo']);

        $this->assertDatabaseHas('favorite_lists', [
            'id' => $list->id,
            'name' => 'Nome novo',
        ]);
    }

    public function test_delete_action_removes_a_custom_list(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Descartável']);

        Livewire::test(Favorites::class)
            ->callTableAction('delete', $list)
            ->assertNotified();

        $this->assertDatabaseMissing('favorite_lists', ['id' => $list->id]);
    }

    public function test_default_list_has_no_delete_action(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $defaultList = $user->defaultFavoriteList();

        Livewire::test(Favorites::class)
            ->assertTableActionHidden('delete', $defaultList);
    }

    public function test_clicking_a_list_links_to_its_dedicated_page(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Minha lista']);

        $url = Livewire::test(Favorites::class)->instance()->getTable()->getRecordUrl($list);

        $this->assertSame(ViewFavoriteList::getUrl(['list' => $list->id]), $url);
    }
}
