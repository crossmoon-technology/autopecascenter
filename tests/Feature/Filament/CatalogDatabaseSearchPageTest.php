<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch\Enums\SearchType;
use App\Filament\Pages\Buscas\ViewPart;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\Part;
use App\Models\QuotationItem\Enums\Source;
use App\Models\SearchHistory;
use App\Models\SearchHistory\Enums\Method;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogDatabaseSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_manufacturer_chips_and_codigo_field(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertSuccessful()
            ->assertFormFieldExists('codigo')
            ->assertSee($manufacturer->name)
            ->assertSet("data.manufacturers.{$manufacturer->id}", true);
    }

    public function test_a_codigo_query_param_prefills_the_field_without_triggering_a_search(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $response = $this->get(CatalogDatabaseSearch::getUrl(['codigo' => '16002'], panel: 'super-admin'));

        $response->assertOk();
        $response->assertSee('16002');
        $this->assertDatabaseCount('search_histories', 0);
    }

    public function test_inactive_manufacturers_are_not_shown_as_chips(): void
    {
        $inactive = Manufacturer::factory()->create(['is_active' => false]);
        Catalog::factory()->create(['manufacturer_id' => $inactive->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertDontSee($inactive->name);
    }

    public function test_only_shows_chips_for_the_users_enabled_manufacturers(): void
    {
        $enabled = Manufacturer::factory()->create(['name' => 'Habilitado', 'is_active' => true]);
        $disabled = Manufacturer::factory()->create(['name' => 'Desabilitado', 'is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $enabled->getKey(), 'is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $disabled->getKey(), 'is_active' => true]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->preferredManufacturers()->attach($enabled);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertSee('Habilitado')
            ->assertDontSee('Desabilitado')
            ->assertSet("data.manufacturers.{$enabled->id}", true)
            ->assertSet("data.manufacturers.{$disabled->id}", null);
    }

    public function test_falls_back_to_every_eligible_manufacturer_when_the_preference_does_not_apply_here(): void
    {
        $unrelatedPreference = Manufacturer::factory()->create(['is_active' => true]);
        $eligible = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $eligible->getKey(), 'is_active' => true]);
        // $unrelatedPreference has no active catalog, so it never appears as a chip here.

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->preferredManufacturers()->attach($unrelatedPreference);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertSet("data.manufacturers.{$eligible->id}", true);
    }

    public function test_manufacturers_without_any_active_catalog_are_not_shown_as_chips(): void
    {
        $withoutCatalogs = Manufacturer::factory()->create(['is_active' => true]);
        $withOnlyInactiveCatalog = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $withOnlyInactiveCatalog->getKey(), 'is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertDontSee($withoutCatalogs->name)
            ->assertDontSee($withOnlyInactiveCatalog->name);
    }

    public function test_search_requires_at_least_one_manufacturer_selected(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->set('data.codigo', '16088')
            ->call('search')
            ->assertNotified();

        $this->assertDatabaseCount('parts', 0);
    }

    public function test_search_requires_codigo(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->call('search')
            ->assertHasFormErrors(['codigo']);
    }

    public function test_search_records_it_in_the_users_history(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16088'])
            ->call('search');

        $this->assertDatabaseHas('search_histories', [
            'user_id' => $user->id,
            'query' => '16088',
            'method' => Method::Database->value,
        ]);
    }

    public function test_search_without_a_selected_manufacturer_is_not_recorded_in_history(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->set('data.codigo', '16088')
            ->call('search');

        $this->assertSame(0, SearchHistory::query()->count());
    }

    public function test_search_does_not_create_or_change_any_records(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16088'])
            ->call('search')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('parts', 0);
    }

    public function test_search_finds_a_part_by_its_own_codigo(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16002',
            'atributos' => ['descricao' => 'MOLA A GÁS'],
            'conversoes' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSee('16002')
            ->assertSee('MOLA A GÁS');
    }

    public function test_search_shows_whatever_attributes_a_different_manufacturer_brings(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => 'HF-21',
            'atributos' => ['montadora' => 'CHEVROLET', 'veiculo' => 'A10'],
            'conversoes' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'HF-21'])
            ->call('search')
            ->assertSee('HF-21')
            ->assertSee('CHEVROLET')
            ->assertSee('A10');
    }

    public function test_search_renders_nested_array_attributes_and_conversoes_without_error(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Sampel', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => 'SP-9',
            'atributos' => [
                'aplicacoes' => [
                    ['montadora' => 'FIAT', 'modelo' => 'UNO'],
                    ['montadora' => 'VW', 'modelo' => 'GOL'],
                ],
            ],
            'conversoes' => ['NAKATA' => [['codigo' => 'N123'], 'N456']],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'SP-9'])
            ->call('search')
            ->assertSuccessful()
            ->assertSee('SP-9')
            ->assertSee('FIAT')
            ->assertSee('N456');
    }

    public function test_search_matches_a_partial_codigo_case_insensitively(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'ABC-16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'abc-160'])
            ->call('search')
            ->assertSee('ABC-16002');
    }

    public function test_search_type_defaults_to_todos(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertFormFieldExists('tipo_busca')
            ->assertSet('data.tipo_busca', SearchType::Todos->value);
    }

    public function test_todos_search_type_matches_by_codigo(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16006',
            'conversoes' => [],
            'atributos' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Todos, 'codigo' => '16006'])
            ->call('search')
            ->assertSee('16006');
    }

    public function test_todos_search_type_matches_by_equivalence_inside_conversoes(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16006',
            'conversoes' => ['NAKATA' => ['MG 19038']],
            'atributos' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Todos, 'codigo' => 'MG 19038'])
            ->call('search')
            ->assertSee('16006')
            ->assertSee('NAKATA');
    }

    public function test_todos_search_type_matches_content_inside_atributos(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => 'HF-21',
            'atributos' => ['montadora' => 'CHEVROLET', 'veiculo' => 'A10'],
            'conversoes' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Todos, 'codigo' => 'CHEVROLET'])
            ->call('search')
            ->assertSee('HF-21')
            ->assertSee('CHEVROLET');
    }

    public function test_codigo_search_type_does_not_match_conversoes_or_atributos(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16006',
            'conversoes' => ['NAKATA' => ['MG 19038']],
            'atributos' => ['descricao' => 'MOLA A GÁS'],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Codigo, 'codigo' => 'MG 19038'])
            ->call('search')
            ->assertSee('Nenhum resultado encontrado em Cofap');
    }

    public function test_equivalentes_search_type_matches_an_equivalence_code_inside_conversoes(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16006',
            'conversoes' => ['NAKATA' => ['MG 19038']],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Equivalentes, 'codigo' => 'MG 19038'])
            ->call('search')
            ->assertSee('16006')
            ->assertSee('NAKATA')
            ->assertSee('MG 19038');
    }

    public function test_equivalentes_search_type_does_not_match_the_parts_own_codigo(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16006',
            'conversoes' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Equivalentes, 'codigo' => '16006'])
            ->call('search')
            ->assertSee('Nenhum resultado encontrado em Cofap');
    }

    public function test_atributos_search_type_matches_content_inside_atributos(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => 'HF-21',
            'atributos' => ['montadora' => 'CHEVROLET', 'veiculo' => 'A10'],
            'conversoes' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Atributos, 'codigo' => 'CHEVROLET'])
            ->call('search')
            ->assertSee('HF-21')
            ->assertSee('CHEVROLET');
    }

    public function test_atributos_search_type_does_not_match_the_parts_own_codigo(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => 'HF-21',
            'atributos' => ['montadora' => 'CHEVROLET'],
            'conversoes' => [],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Atributos, 'codigo' => 'HF-21'])
            ->call('search')
            ->assertSee('Nenhum resultado encontrado em Hipper Freios');
    }

    public function test_search_only_returns_parts_for_selected_manufacturers(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $sabo = Manufacturer::factory()->create(['name' => 'Sabó', 'is_active' => true]);
        $cofapCatalog = Catalog::factory()->create(['manufacturer_id' => $cofap->getKey(), 'is_active' => true]);
        $saboCatalog = Catalog::factory()->create(['manufacturer_id' => $sabo->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $cofapCatalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        Part::factory()->create(['catalog_id' => $saboCatalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$sabo->id}", false)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSet("results.{$cofap->id}.0.codigo", '16002')
            ->assertSet("results.{$sabo->id}", null);
    }

    public function test_search_ignores_parts_from_an_inactive_catalog(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $activeCatalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $inactiveCatalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => false]);
        Part::factory()->create(['catalog_id' => $inactiveCatalog->getKey(), 'codigo' => 'HIDDEN-123', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'HIDDEN-123'])
            ->call('search')
            ->assertDontSee('HIDDEN-123')
            ->assertSee('Nenhum resultado encontrado');
    }

    public function test_search_shows_an_empty_state_when_nothing_matches(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'nao-existe'])
            ->call('search')
            ->assertSee('Nenhum resultado encontrado em Cofap');
    }

    public function test_toggle_favorite_adds_and_removes_a_part(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        $component = Livewire::test(CatalogDatabaseSearch::class)
            ->call('toggleFavorite', $part->id)
            ->assertSet('favoritedPartIds', [$part->id]);

        $this->assertDatabaseHas('favorite_list_part', [
            'favorite_list_id' => $user->defaultFavoriteList()->id,
            'part_id' => $part->id,
        ]);

        $component->call('toggleFavorite', $part->id)
            ->assertSet('favoritedPartIds', []);

        $this->assertDatabaseMissing('favorite_list_part', [
            'favorite_list_id' => $user->defaultFavoriteList()->id,
            'part_id' => $part->id,
        ]);
    }

    public function test_mount_preloads_already_favorited_parts(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->defaultFavoriteList()->parts()->attach($part);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertSet('favoritedPartIds', [$part->id]);
    }

    public function test_search_results_show_a_favorite_toggle_for_each_part(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSeeHtml("wire:click=\"toggleFavorite({$part->id})\"");
    }

    public function test_part_view_url_points_to_that_parts_dedicated_page(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $url = Livewire::test(CatalogDatabaseSearch::class)->instance()->partViewUrl($part);

        $this->assertSame(ViewPart::getUrl(['record' => $part->id]), $url);
    }

    public function test_search_results_link_the_codigo_to_the_parts_dedicated_page(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSeeHtml('href="'.ViewPart::getUrl(['record' => $part->id]).'"');
    }

    public function test_search_results_share_buttons_use_the_public_signed_url(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSeeHtml('/p/'.$part->id.'?expires=');
    }

    public function test_suggests_favoriting_after_three_repeated_searches(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $component = Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->fillForm(['codigo' => '16002'])
            ->call('search');

        // Ainda não bateu o limiar — nenhuma sugestão.
        $component->assertNotNotified();

        $component->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertNotified('Você já buscou "16002" 3 vezes');
    }

    public function test_does_not_suggest_favoriting_when_the_part_is_already_favorited(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->defaultFavoriteList()->parts()->attach($part);
        $this->actingAs($user);

        $component = Livewire::test(CatalogDatabaseSearch::class);

        for ($i = 0; $i < 3; $i++) {
            $component->fillForm(['codigo' => '16002'])->call('search');
        }

        $component->assertNotNotified();
    }

    public function test_favorite_part_from_suggestion_event_favorites_the_part(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->call('favoritePartFromSuggestion', $part->id)
            ->assertSet('favoritedPartIds', [$part->id]);

        $this->assertDatabaseHas('favorite_list_part', [
            'favorite_list_id' => $user->defaultFavoriteList()->id,
            'part_id' => $part->id,
        ]);
    }

    public function test_pick_favorite_list_action_adds_the_part_to_the_chosen_list(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Orçamento Cliente X']);

        Livewire::test(CatalogDatabaseSearch::class)
            ->callAction('pickFavoriteList', data: ['favorite_list_id' => $list->id], arguments: ['part_id' => $part->id])
            ->assertNotified()
            ->assertSet('favoritedPartIds', [$part->id]);

        $this->assertTrue($list->parts()->whereKey($part->id)->exists());
    }

    public function test_add_to_quotation_creates_an_open_quotation_with_the_part(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->call('addToQuotation', $part->id)
            ->assertNotified()
            ->assertSet('quotedPartIds', [$part->id]);

        $this->assertDatabaseHas('quotation_items', [
            'part_id' => $part->id,
            'manufacturer_id' => $manufacturer->id,
            'source' => Source::Database->value,
            'codigo' => '16002',
        ]);
    }

    public function test_adding_the_same_part_to_the_quotation_twice_toggles_it_back_off(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->call('addToQuotation', $part->id)
            ->call('addToQuotation', $part->id)
            ->assertSet('quotedPartIds', []);

        $this->assertSame(0, $user->openQuotation()->items()->where('part_id', $part->id)->count());
    }

    public function test_mount_preloads_parts_already_in_the_open_quotation(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $user->openQuotation()->addPart($part);

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertSet('quotedPartIds', [$part->id]);
    }

    public function test_search_history_records_found_results_as_true_when_something_matches(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search');

        $this->assertDatabaseHas('search_histories', [
            'user_id' => $user->id,
            'query' => '16002',
            'found_results' => true,
        ]);
    }

    public function test_search_history_records_found_results_as_false_when_nothing_matches(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'nao-existe'])
            ->call('search');

        $this->assertDatabaseHas('search_histories', [
            'user_id' => $user->id,
            'query' => 'nao-existe',
            'found_results' => false,
        ]);
    }
}
