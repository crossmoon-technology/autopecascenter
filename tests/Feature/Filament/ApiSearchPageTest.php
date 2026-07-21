<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Api;
use App\Filament\Pages\Buscas\Api\Enums\SearchStatus;
use App\Models\Manufacturer;
use App\Models\QuotationItem\Enums\Source;
use App\Models\SearchHistory\Enums\Method;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ApiSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_manufacturer_chips_and_codigo_field(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        Manufacturer::factory()->create(['name' => 'Wega', 'slug' => 'wega', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Api::class)
            ->assertSuccessful()
            ->assertFormFieldExists('codigo')
            ->assertSee('Cofap')
            ->assertDontSee('Wega')
            ->assertSet("data.manufacturers.{$cofap->id}", true);
    }

    public function test_a_codigo_query_param_prefills_the_field_without_triggering_a_search(): void
    {
        Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake();

        $response = $this->get(Api::getUrl(['codigo' => 'HF-21'], panel: 'super-admin'));

        $response->assertOk();
        $response->assertSee('HF-21');
        $this->assertDatabaseCount('search_histories', 0);
        Http::assertNothingSent();
    }

    public function test_ignores_inactive_manufacturers_even_with_a_provider(): void
    {
        Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Api::class)->assertDontSee('Cofap');
    }

    public function test_only_shows_chips_for_the_users_enabled_manufacturers(): void
    {
        $enabled = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $disabled = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'slug' => 'hipper-freios', 'is_active' => true]);

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->preferredManufacturers()->attach($enabled);
        $this->actingAs($user);

        Livewire::test(Api::class)
            ->assertSee('Cofap')
            ->assertDontSee('Hipper Freios')
            ->assertSet("data.manufacturers.{$enabled->id}", true)
            ->assertSet("data.manufacturers.{$disabled->id}", null);
    }

    public function test_falls_back_to_every_eligible_manufacturer_when_the_preference_does_not_apply_here(): void
    {
        $unrelatedPreference = Manufacturer::factory()->create(['name' => 'Wega', 'slug' => 'wega', 'is_active' => false]);
        $eligible = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        // Wega is inactive, so it's never searchable and never appears as a chip here.

        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->preferredManufacturers()->attach($unrelatedPreference);
        $this->actingAs($user);

        Livewire::test(Api::class)
            ->assertSet("data.manufacturers.{$eligible->id}", true);
    }

    public function test_search_requires_at_least_one_manufacturer_selected(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake();

        Livewire::test(Api::class)
            ->set("data.manufacturers.{$cofap->id}", false)
            ->set('data.codigo', '16002')
            ->call('search')
            ->assertNotified();

        Http::assertNothingSent();
    }

    public function test_search_requires_codigo(): void
    {
        Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake();

        Livewire::test(Api::class)
            ->call('search')
            ->assertHasFormErrors(['codigo']);

        Http::assertNothingSent();
    }

    public function test_search_puts_every_selected_manufacturer_into_a_loading_state_without_fetching_yet(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $hipperFreios = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'slug' => 'hipper-freios', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake();

        Livewire::test(Api::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertHasNoFormErrors()
            ->assertSet("results.{$cofap->id}.status", SearchStatus::Loading)
            ->assertSet("results.{$hipperFreios->id}.status", SearchStatus::Loading)
            ->assertSee('Buscando em Cofap');

        Http::assertNothingSent();
    }

    public function test_search_records_it_in_the_users_history(): void
    {
        Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Http::fake();

        Livewire::test(Api::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search');

        $this->assertDatabaseHas('search_histories', [
            'user_id' => $user->id,
            'query' => '16002',
            'method' => Method::Api->value,
        ]);
    }

    public function test_search_manufacturer_populates_that_manufacturers_tab_with_its_results(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $hipperFreios = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'slug' => 'hipper-freios', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake([
            'mmcofap.com.br/*' => Http::response(<<<'HTML'
                <section class="resultados-busca">
                    <div class="specs specs-busca linha-1">
                        <div class="imagem"><img class="trigger-lightbox" src="https://mmcofap.com.br/img/16002.jpg"></div>
                        <div class = 'item-title'>
                            <a href="https://mmcofap.com.br/busca-catalogo/?busca=16002" target="_blank">AMORTECEDOR 16002</a><i>VOLKSWAGEN - </i>GOL</a>
                        </div>
                    </div>
                </section>
                HTML, 200),
            'hipperfreios.com.br/*' => Http::response('<table class="tabela-busca"></table>', 200),
        ]);

        $component = Livewire::test(Api::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertHasNoFormErrors();

        // Only Cofap was fetched so far — Hipper Freios stays in "loading" state,
        // proving the tabs populate one at a time instead of all at once.
        $component->call('searchManufacturer', $cofap->id)
            ->assertSet("results.{$cofap->id}.status", SearchStatus::Success)
            ->assertSet("results.{$hipperFreios->id}.status", SearchStatus::Loading)
            ->assertSee('16002')
            ->assertSee('AMORTECEDOR 16002')
            ->assertSee('Buscando em Hipper Freios');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'hipperfreios.com.br'));

        $component->call('searchManufacturer', $hipperFreios->id)
            ->assertSet("results.{$hipperFreios->id}.status", SearchStatus::Success)
            ->assertSee('Nenhum resultado encontrado em Hipper Freios');
    }

    public function test_a_failing_supplier_does_not_break_the_other_results(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $hipperFreios = Manufacturer::factory()->create(['name' => 'Hipper Freios', 'slug' => 'hipper-freios', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake([
            'mmcofap.com.br/*' => Http::response('', 500),
            'hipperfreios.com.br/*' => Http::response(<<<'HTML'
                <table class="tabela-busca">
                    <tr valign="middle" class="hover-table transition" data-href="/pt-br/produto/hf-21">
                        <td><img src="./slir/hf21.png"></td>
                        <td>CHEVROLET</td>
                        <td>A10</td>
                        <td>TODOS</td>
                        <td>1986 até 1999</td>
                        <td>Disco de Freio</td>
                        <td>HF 21</td>
                    </tr>
                </table>
                HTML, 200),
        ]);

        Livewire::test(Api::class)
            ->fillForm(['codigo' => 'freio'])
            ->call('search')
            ->call('searchManufacturer', $cofap->id)
            ->assertSet("results.{$cofap->id}.status", SearchStatus::Failed)
            ->assertSee('Não foi possível buscar em Cofap agora')
            ->call('searchManufacturer', $hipperFreios->id)
            ->assertSet("results.{$hipperFreios->id}.status", SearchStatus::Success)
            ->assertSee('HF 21');
    }

    public function test_deselecting_a_manufacturer_excludes_it_from_the_search(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        Manufacturer::factory()->create(['name' => 'Hipper Freios', 'slug' => 'hipper-freios', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake();

        Livewire::test(Api::class)
            ->set("data.manufacturers.{$cofap->id}", false)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->assertDontSee('Buscando em Cofap')
            ->assertSee('Buscando em Hipper Freios');

        Http::assertNothingSent();
    }

    public function test_item_share_url_points_back_to_this_page_with_the_codigo(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $url = Livewire::test(Api::class)->instance()->itemShareUrl('16002');

        $this->assertStringContainsString('codigo=16002', $url);
    }

    public function test_add_to_quotation_creates_an_open_quotation_with_the_external_item(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(Api::class)
            ->call('addToQuotation', $cofap->id, '16002', 'AMORTECEDOR 16002')
            ->assertNotified()
            ->assertSet('quotedApiKeys', ["{$cofap->id}|16002"]);

        $this->assertDatabaseHas('quotation_items', [
            'part_id' => null,
            'manufacturer_id' => $cofap->id,
            'source' => Source::Api->value,
            'codigo' => '16002',
            'descricao' => 'AMORTECEDOR 16002',
        ]);
    }

    public function test_adding_the_same_external_item_twice_toggles_it_back_off(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(Api::class)
            ->call('addToQuotation', $cofap->id, '16002', 'AMORTECEDOR 16002')
            ->call('addToQuotation', $cofap->id, '16002', 'AMORTECEDOR 16002')
            ->assertSet('quotedApiKeys', []);

        $this->assertDatabaseCount('quotation_items', 0);
    }

    public function test_search_results_show_a_share_link_for_each_item(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap', 'slug' => 'cofap', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Http::fake([
            'mmcofap.com.br/*' => Http::response(<<<'HTML'
                <section class="resultados-busca">
                    <div class="specs specs-busca linha-1">
                        <div class="imagem"><img class="trigger-lightbox" src="https://mmcofap.com.br/img/16002.jpg"></div>
                        <div class = 'item-title'>
                            <a href="https://mmcofap.com.br/busca-catalogo/?busca=16002" target="_blank">AMORTECEDOR 16002</a><i>VOLKSWAGEN - </i>GOL</a>
                        </div>
                    </div>
                </section>
                HTML, 200),
        ]);

        Livewire::test(Api::class)
            ->fillForm(['codigo' => '16002'])
            ->call('search')
            ->call('searchManufacturer', $cofap->id)
            ->assertSeeHtml('Compartilhar no WhatsApp');
    }
}
