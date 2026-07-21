<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\ViewFavoriteList;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\Part;
use App\Models\QuotationItem\Enums\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewFavoriteListPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_does_not_appear_in_navigation(): void
    {
        $this->assertFalse(ViewFavoriteList::shouldRegisterNavigation());
    }

    public function test_shows_the_parts_in_that_list(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Minha lista']);
        $list->parts()->attach($part);

        Livewire::test(ViewFavoriteList::class, ['list' => $list->id])
            ->assertSuccessful()
            ->assertSee('16002');
    }

    public function test_returns_404_for_a_list_that_belongs_to_another_user(): void
    {
        $otherUser = User::factory()->create(['role' => Role::SuperAdmin]);
        $list = $otherUser->favoriteLists()->create(['name' => 'Não é sua']);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->get(ViewFavoriteList::getUrl(['list' => $list->id], panel: 'super-admin'))
            ->assertNotFound();
    }

    public function test_shows_an_empty_state_when_the_list_has_no_parts(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Vazia']);

        Livewire::test(ViewFavoriteList::class, ['list' => $list->id])
            ->assertSuccessful()
            ->assertSee('Nenhuma peça nessa lista ainda');
    }

    public function test_remove_action_detaches_the_part_from_the_list(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Minha lista']);
        $list->parts()->attach($part);

        Livewire::test(ViewFavoriteList::class, ['list' => $list->id])
            ->callTableAction('remove', $part)
            ->assertNotified();

        $this->assertFalse($list->parts()->whereKey($part->id)->exists());
    }

    public function test_edit_note_action_saves_a_note(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Minha lista']);
        $list->parts()->attach($part);

        Livewire::test(ViewFavoriteList::class, ['list' => $list->id])
            ->callTableAction('editNote', $part, data: ['note' => 'Cliente pede sempre essa'])
            ->assertNotified();

        $this->assertDatabaseHas('favorite_list_part', [
            'favorite_list_id' => $list->id,
            'part_id' => $part->id,
            'note' => 'Cliente pede sempre essa',
        ]);
    }

    public function test_edit_note_action_prefills_the_existing_note(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Minha lista']);
        $list->parts()->attach($part, ['note' => 'Nota antiga']);

        Livewire::test(ViewFavoriteList::class, ['list' => $list->id])
            ->mountTableAction('editNote', $part)
            ->assertTableActionDataSet(['note' => 'Nota antiga']);
    }

    public function test_add_to_quotation_action_creates_an_open_quotation_with_the_part(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Minha lista']);
        $list->parts()->attach($part);

        Livewire::test(ViewFavoriteList::class, ['list' => $list->id])
            ->callTableAction('addToQuotation', $part)
            ->assertNotified();

        $this->assertDatabaseHas('quotation_items', [
            'part_id' => $part->id,
            'manufacturer_id' => $manufacturer->id,
            'source' => Source::Database->value,
            'codigo' => '16002',
        ]);
    }
}
