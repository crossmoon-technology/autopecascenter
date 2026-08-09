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
use App\Services\PartEquivalence\RebuildPartEquivalences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    /**
     * Exceção à regra acima: um fabricante com um provedor de busca ao vivo registrado
     * (ver PartSearchProviderRegistry) aparece mesmo sem catálogo nenhum — é assim que
     * MTE-Thomson (sem raspagem em bloco viável) fica selecionável.
     */
    public function test_a_manufacturer_with_a_live_search_provider_is_shown_even_without_a_catalog(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true, 'part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->assertSee($manufacturer->name)
            ->assertSet("data.manufacturers.{$manufacturer->id}", true);
    }

    /**
     * search() só devolve os ids pendentes — não faz a busca ao vivo ela mesma (isso é
     * fetchLiveResultFor(), disparado pelo JS da view DEPOIS que essa resposta já
     * chegou) — é isso que deixa a aba aparecer com o spinner na hora, sem o
     * formulário inteiro travar esperando o site do fabricante responder.
     */
    public function test_search_immediately_shows_a_pending_tab_with_a_loading_indicator(): void
    {
        Http::fake(['cate.mte-thomson.com.br/*' => Http::response('', 200)]);

        $manufacturer = Manufacturer::factory()->create(['is_active' => true, 'part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $ids = Livewire::test(CatalogDatabaseSearch::class)
            ->set('data.codigo', '206.82')
            ->call('search')
            ->assertSet('liveSearchPending', [$manufacturer->id])
            ->assertSee('Buscando ao vivo')
            ->assertSee($manufacturer->name)
            ->get('liveSearchPending');

        $this->assertSame([$manufacturer->id], $ids);
        Http::assertNothingSent();
    }

    public function test_search_shows_live_results_for_a_manufacturer_without_a_catalog(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response(<<<'HTML'
                <html><body><table><tbody>
                <tr class="grid-row">
                    <td class="grid-cell" data-name="" style="display:none;"></td>
                    <td class="grid-cell" data-name="" style="display:none;"></td>
                    <td class="grid-cell" data-name=""><a href="/pt/br/produto/detalhes/206.82/slug"><img src="img.jpg" /></a></td>
                    <td class="grid-cell" data-name="PARTNUMBER"><a href="/pt/br/produto/detalhes/206.82/slug">206.82</a></td>
                    <td class="grid-cell" data-name="NOME_LINHA_PRODUTO"><a href="/pt/br/produto/detalhes/206.82/slug">VÁLVULA TERMOSTÁTICA</a></td>
                    <td class="grid-cell" data-name=""><ul class="list-unstyled"></ul></td>
                    <td class="grid-cell" data-name=""><label></label></td>
                    <td class="grid-cell" data-name=""><ul class="list-unstyled"></ul></td>
                </tr>
                </tbody></table></body></html>
                HTML, 200),
        ]);

        $manufacturer = Manufacturer::factory()->create(['is_active' => true, 'part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        // A busca ao vivo roda como uma chamada separada, disparada pelo JS da view
        // DEPOIS que search() já retornou (ver o wire:submit no blade) — o teste
        // encadeia manualmente o que o navegador faria, pra aba aparecer com o
        // spinner primeiro e só depois os resultados de verdade.
        Livewire::test(CatalogDatabaseSearch::class)
            ->set('data.codigo', '206.82')
            ->call('search')
            ->assertSee('Buscando ao vivo')
            ->call('fetchLiveResultFor', $manufacturer->id)
            ->assertSee('206.82')
            ->assertSee('VÁLVULA TERMOSTÁTICA');
    }

    /**
     * O site do fabricante pagina os resultados dele (ver MteThomsonPartSearchProvider)
     * — o pager só aparece quando tem mais de uma página, e trocar de página chama
     * fetchLiveResultFor() de novo com o número da página pedida.
     */
    public function test_live_search_pagination_shows_pager_and_navigates_pages(): void
    {
        $row = fn (string $codigo) => <<<HTML
            <tr class="grid-row">
                <td class="grid-cell" data-name="" style="display:none;"></td>
                <td class="grid-cell" data-name="" style="display:none;"></td>
                <td class="grid-cell" data-name=""><a href="/pt/br/produto/detalhes/{$codigo}/slug"><img src="img.jpg" /></a></td>
                <td class="grid-cell" data-name="PARTNUMBER"><a href="/pt/br/produto/detalhes/{$codigo}/slug">{$codigo}</a></td>
                <td class="grid-cell" data-name="NOME_LINHA_PRODUTO"><a href="/pt/br/produto/detalhes/{$codigo}/slug">DESC {$codigo}</a></td>
                <td class="grid-cell" data-name=""><ul class="list-unstyled"></ul></td>
                <td class="grid-cell" data-name=""><label></label></td>
                <td class="grid-cell" data-name=""><ul class="list-unstyled"></ul></td>
            </tr>
            HTML;

        $footer = <<<'HTML'
            <div class="grid-footer">
                <label class="custom-color-cinza">Total de itens:</label> <label class="custom-color-cinza">101</label>
                <ul class="pagination">
                    <li class="page-item"><a class="page-link" href="?grid-page=1">1</a></li>
                    <li class="page-item"><a class="page-link" href="?grid-page=9">9</a></li>
                </ul>
            </div>
            HTML;

        Http::fake([
            'cate.mte-thomson.com.br/*grid-page=2*' => Http::response('<html><body><table><tbody>'.$row('P2').'</tbody></table>'.$footer.'</body></html>', 200),
            'cate.mte-thomson.com.br/*' => Http::response('<html><body><table><tbody>'.$row('P1').'</tbody></table>'.$footer.'</body></html>', 200),
        ]);

        $manufacturer = Manufacturer::factory()->create(['is_active' => true, 'part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set('data.codigo', '100')
            ->call('search')
            ->call('fetchLiveResultFor', $manufacturer->id)
            ->assertSee('P1')
            ->assertSee('Página 1 de 9')
            ->assertSee('Total de itens: 101')
            ->call('fetchLiveResultFor', $manufacturer->id, 2)
            ->assertSee('P2')
            ->assertDontSee('P1')
            ->assertSee('Página 2 de 9');
    }

    /**
     * Uma falha na busca ao vivo (site fora do ar, timeout) não pode aparecer como
     * "nenhum resultado encontrado" — o vendedor precisa saber que é um erro
     * temporário, não que a peça não existe.
     */
    public function test_live_search_failure_shows_a_notice_instead_of_no_results(): void
    {
        Http::fake([
            'cate.mte-thomson.com.br/*' => Http::response('', 500),
        ]);

        $manufacturer = Manufacturer::factory()->create(['is_active' => true, 'part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set('data.codigo', '206.82')
            ->call('search')
            ->call('fetchLiveResultFor', $manufacturer->id)
            ->assertSee('Não foi possível buscar ao vivo')
            ->assertDontSee('Nenhum resultado encontrado');
    }

    public function test_add_live_result_to_quotation_creates_an_external_quotation_item(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true, 'part_search_slug' => 'mte-thomson']);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            ->call('addLiveResultToQuotation', $manufacturer->id, '206.82', 'VÁLVULA TERMOSTÁTICA');

        $this->assertDatabaseHas('quotation_items', [
            'source' => Source::Api,
            'manufacturer_id' => $manufacturer->id,
            'codigo' => '206.82',
            'descricao' => 'VÁLVULA TERMOSTÁTICA',
            'part_id' => null,
        ]);
    }

    public function test_add_live_result_to_quotation_twice_toggles_it_back_off(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true, 'part_search_slug' => 'mte-thomson']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $component = Livewire::test(CatalogDatabaseSearch::class);
        $component->call('addLiveResultToQuotation', $manufacturer->id, '206.82', 'VÁLVULA TERMOSTÁTICA');
        $component->call('addLiveResultToQuotation', $manufacturer->id, '206.82', 'VÁLVULA TERMOSTÁTICA');

        $this->assertDatabaseMissing('quotation_items', [
            'source' => Source::Api,
            'manufacturer_id' => $manufacturer->id,
            'codigo' => '206.82',
        ]);
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

    public function test_search_deletes_the_oldest_history_entry_once_the_user_has_100(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        $oldest = null;

        foreach (range(1, 100) as $i) {
            $record = SearchHistory::factory()->create(['user_id' => $user->id, 'query' => "codigo-{$i}"]);
            $record->timestamps = false;
            $record->created_at = now()->subMinutes(200 - $i);
            $record->save();

            if ($i === 1) {
                $oldest = $record;
            }
        }

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16088'])
            ->call('search');

        $this->assertSame(100, SearchHistory::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('search_histories', ['id' => $oldest->id]);
        $this->assertDatabaseHas('search_histories', ['user_id' => $user->id, 'query' => '16088']);
    }

    public function test_search_without_a_selected_manufacturer_is_still_recorded_in_history(): void
    {
        // "Peças exatas" ignora o checkbox de fabricantes de propósito (busca em todos
        // sempre) — então uma busca sem nenhum fabricante marcado ainda é uma busca de
        // verdade, e continua sendo registrada no histórico.
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->set('data.codigo', '16088')
            ->call('search');

        $this->assertSame(1, SearchHistory::query()->count());
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
            // Normalizado ao salvar (ver Part::booted()): maiúsculo, sem espaço.
            ->assertSee('MG19038');
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

    public function test_pecas_exatas_finds_a_part_by_its_own_codigo(): void
    {
        // Desmarcado de propósito: um fabricante marcado já tem seu resultado coberto em
        // "Resultados" e agora fica de fora de "Peças exatas" (ver
        // test_pecas_exatas_excludes_a_manufacturer_that_is_checked).
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey($part->id)->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSet("exactResults.{$manufacturer->id}.0.id", $part->id)
            ->assertSee('Peças exatas');
    }

    public function test_pecas_exatas_finds_a_part_regardless_of_the_typed_case_or_spacing(): void
    {
        // A peça é salva normalizada (MG19038, sem espaço — ver Part::booted()), mas o
        // vendedor pode digitar do jeito que o cliente falou, com espaço e minúsculo.
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'MG19038', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey($part->id)->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->fillForm(['codigo' => 'mg 19038'])
            ->call('search')
            ->assertSet("exactResults.{$manufacturer->id}.0.id", $part->id);
    }

    public function test_resultados_section_finds_a_part_regardless_of_the_typed_case_or_spacing(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'MG19038', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Codigo, 'codigo' => 'mg 19038'])
            ->call('search')
            ->assertSee('MG19038');
    }

    public function test_pecas_exatas_includes_a_manufacturer_that_is_unchecked(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey($part->id)->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSet("exactResults.{$manufacturer->id}.0.id", $part->id)
            ->assertSet('results', []);
    }

    /**
     * Reproduz o bug relatado: um vendedor que só habilitou "Cofap" em Configurações não
     * via a peça exata de um código de "Hiper Freios" nem em "Peças exatas" — porque
     * allActivePartsQuery() usava activeManufacturers() (já filtrado pela preferência do
     * vendedor), então Hiper Freios nunca entrava no universo pesquisado ali.
     */
    public function test_pecas_exatas_finds_a_part_from_a_manufacturer_the_seller_has_not_enabled(): void
    {
        $enabled = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $enabled->getKey(), 'is_active' => true]);

        $notEnabled = Manufacturer::factory()->create(['name' => 'Hiper Freios', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $notEnabled->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'HF01', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey($part->id)->get());

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->preferredManufacturers()->attach($enabled);
        $this->actingAs($user);

        Livewire::test(CatalogDatabaseSearch::class)
            // Hiper Freios nem aparece como chip pra esse vendedor — não tem como desmarcar
            // o que não existe na tela, e ainda assim a peça exata precisa aparecer.
            ->assertDontSee('Hiper Freios')
            ->fillForm(['codigo' => 'HF01'])
            ->call('search')
            ->assertSet("exactResults.{$notEnabled->id}.0.id", $part->id)
            ->assertSee('Hiper Freios');
    }

    /**
     * Um fabricante marcado já aparece em "Resultados" — mostrar ele de novo em "Peças
     * exatas" duplicaria o mesmo dado na tela (ver conversa que motivou esse ajuste).
     */
    public function test_pecas_exatas_excludes_a_manufacturer_that_is_checked(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Catalog::query()->find($catalog->id)->parts);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSet("results.{$manufacturer->id}.0.codigo", '16002')
            ->assertSet('exactResults', []);
    }

    public function test_pecas_exatas_does_not_match_a_longer_code(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $exact = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'GH 123', 'conversoes' => []]);
        $longer = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'GH 1234', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey([$exact->id, $longer->id])->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->fillForm(['codigo' => 'GH 123'])
            ->call('search')
            ->assertSet("exactResults.{$manufacturer->id}", fn ($parts) => $parts->count() === 1 && $parts->first()->id === $exact->id);
    }

    public function test_pecas_exatas_shows_chained_equivalents_across_manufacturers(): void
    {
        $bosch = Manufacturer::factory()->create(['name' => 'Bosch', 'is_active' => true]);
        $nakata = Manufacturer::factory()->create(['name' => 'Nakata', 'is_active' => true]);
        $boschCatalog = Catalog::factory()->create(['manufacturer_id' => $bosch->getKey(), 'is_active' => true]);
        $nakataCatalog = Catalog::factory()->create(['manufacturer_id' => $nakata->getKey(), 'is_active' => true]);

        $boschPart = Part::factory()->create([
            'catalog_id' => $boschCatalog->getKey(),
            'codigo' => 'BOSCH-001',
            'conversoes' => ['NAKATA' => ['MG 19038']],
        ]);
        $nakataPart = Part::factory()->create(['catalog_id' => $nakataCatalog->getKey(), 'codigo' => 'MG 19038', 'conversoes' => []]);

        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey([$boschPart->id, $nakataPart->id])->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$bosch->id}", false)
            ->set("data.manufacturers.{$nakata->id}", false)
            ->fillForm(['codigo' => 'BOSCH-001'])
            ->call('search')
            ->assertSet("exactResults.{$bosch->id}.0.id", $boschPart->id)
            ->assertSet("exactResults.{$nakata->id}.0.id", $nakataPart->id);
    }

    public function test_clicking_an_equivalence_chip_in_pecas_exatas_searches_for_it(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $boschPart = Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => 'BOSCH-001',
            'conversoes' => ['NAKATA' => ['MG 19038']],
        ]);
        $nakataPart = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'MG 19038', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey([$boschPart->id, $nakataPart->id])->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->call('searchFor', 'MG 19038')
            ->assertSet('data.codigo', 'MG 19038')
            ->assertSet("exactResults.{$manufacturer->id}.0.id", $nakataPart->id);
    }

    public function test_pecas_exatas_shows_a_notice_when_the_fallback_scan_was_used(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        // Não indexado de propósito.

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSet('usedFallbackScan', true)
            ->assertSee('ainda não está no índice rápido de busca');
    }

    public function test_pecas_exatas_does_not_show_the_fallback_notice_when_nothing_matches_at_all(): void
    {
        // Nenhuma peça com esse código existe (indexada ou não) — isso é um resultado
        // normal de "não encontrado", não um sinal de índice desatualizado, então o
        // aviso não deveria aparecer.
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'CODIGO-QUE-NAO-EXISTE'])
            ->call('search')
            ->assertSet('usedFallbackScan', false)
            ->assertDontSee('ainda não está no índice rápido de busca');
    }

    public function test_pecas_exatas_ignores_parts_from_an_inactive_manufacturer(): void
    {
        $inactiveManufacturer = Manufacturer::factory()->create(['is_active' => false]);
        $activeManufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $activeManufacturer->getKey(), 'is_active' => true]);
        $inactiveCatalog = Catalog::factory()->create(['manufacturer_id' => $inactiveManufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $inactiveCatalog->getKey(), 'codigo' => 'HIDDEN-99', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey($part->id)->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'HIDDEN-99'])
            ->call('search')
            ->assertSet('exactResults', []);
    }

    public function test_highlights_the_searched_term_inside_the_matching_codigo(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'GP33314', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'GP333'])
            ->call('search')
            ->assertSeeHtml('<mark class="pe-highlight">GP333</mark>14');
    }

    public function test_highlights_the_searched_term_inside_an_equivalence_value(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => 'GP33314',
            'conversoes' => ['NAKATA' => ['HG 41297']],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['tipo_busca' => SearchType::Equivalentes, 'codigo' => 'HG 41297'])
            ->call('search')
            // Normalizado ao salvar (ver Part::booted()): maiúsculo, sem espaço — o
            // termo buscado (com espaço) ainda bate graças ao \s* opcional em highlight().
            ->assertSeeHtml('<mark class="pe-highlight">HG41297</mark>');
    }

    public function test_highlight_is_case_insensitive(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'GP33314', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'gp333'])
            ->call('search')
            ->assertSeeHtml('<mark class="pe-highlight">GP333</mark>14');
    }

    public function test_highlight_appears_in_pecas_exatas_too(): void
    {
        // Desmarcado de propósito, senão o destaque viria de "Resultados" e o teste não
        // provaria nada sobre "Peças exatas" especificamente.
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => 'GP33314', 'conversoes' => []]);
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereKey($part->id)->get());

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->set("data.manufacturers.{$manufacturer->id}", false)
            ->fillForm(['codigo' => 'GP33314'])
            ->call('search')
            ->assertSeeHtml('<mark class="pe-highlight">GP33314</mark>');
    }

    public function test_manufacturer_tab_is_highlighted_when_it_has_results(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertSeeHtml('class="pe-tab  pe-tab-has-results "');
    }

    public function test_manufacturer_tab_is_not_highlighted_when_it_has_no_results(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CatalogDatabaseSearch::class)
            ->fillForm(['codigo' => 'nao-existe'])
            ->call('search')
            ->assertDontSeeHtml('class="pe-tab  pe-tab-has-results "');
    }

    public function test_no_highlight_before_any_search_has_run(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $highlighted = Livewire::test(CatalogDatabaseSearch::class)->instance()->highlight('GP33314');

        $this->assertSame('GP33314', $highlighted);
    }
}
