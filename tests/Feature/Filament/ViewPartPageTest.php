<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\ViewPart;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\Part;
use App\Models\QuotationItem\Enums\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewPartPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_does_not_appear_in_navigation(): void
    {
        $this->assertFalse(ViewPart::shouldRegisterNavigation());
    }

    public function test_page_shows_the_parts_details(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Cofap', 'is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'name' => 'Suspensão', 'is_active' => true]);
        $part = Part::factory()->create([
            'catalog_id' => $catalog->getKey(),
            'codigo' => '16002',
            'atributos' => ['descricao' => 'MOLA A GÁS'],
            'conversoes' => ['NAKATA' => ['N123']],
        ]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ViewPart::class, ['record' => $part->id])
            ->assertSuccessful()
            ->assertSee('16002')
            ->assertSee('Cofap')
            ->assertSee('Suspensão')
            ->assertSee('MOLA A GÁS')
            ->assertSee('NAKATA')
            ->assertSee('N123');
    }

    public function test_page_returns_404_for_a_nonexistent_part(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->get(ViewPart::getUrl(['record' => 999999], panel: 'super-admin'))
            ->assertNotFound();
    }

    public function test_toggle_favorite_adds_and_removes_the_part(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        $component = Livewire::test(ViewPart::class, ['record' => $part->id])
            ->assertSet('isFavorited', false)
            ->call('toggleFavorite')
            ->assertSet('isFavorited', true);

        $this->assertDatabaseHas('favorite_list_part', [
            'favorite_list_id' => $user->defaultFavoriteList()->id,
            'part_id' => $part->id,
        ]);

        $component->call('toggleFavorite')->assertSet('isFavorited', false);

        $this->assertDatabaseMissing('favorite_list_part', [
            'favorite_list_id' => $user->defaultFavoriteList()->id,
            'part_id' => $part->id,
        ]);
    }

    public function test_starts_favorited_when_the_user_already_favorited_it(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $user->defaultFavoriteList()->parts()->attach($part);
        $this->actingAs($user);

        Livewire::test(ViewPart::class, ['record' => $part->id])
            ->assertSet('isFavorited', true);
    }

    public function test_pick_favorite_list_action_adds_the_part_to_the_chosen_list(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $list = $user->favoriteLists()->create(['name' => 'Orçamento Cliente X']);

        Livewire::test(ViewPart::class, ['record' => $part->id])
            ->callAction('pickFavoriteList', data: ['favorite_list_id' => $list->id], arguments: ['part_id' => $part->id])
            ->assertNotified()
            ->assertSet('isFavorited', true);

        $this->assertTrue($list->parts()->whereKey($part->id)->exists());
    }

    public function test_add_to_quotation_creates_an_open_quotation_with_the_part(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'codigo' => '16002', 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(ViewPart::class, ['record' => $part->id])
            ->assertSet('isQuoted', false)
            ->call('addToQuotation')
            ->assertNotified()
            ->assertSet('isQuoted', true);

        $this->assertDatabaseHas('quotation_items', [
            'part_id' => $part->id,
            'manufacturer_id' => $manufacturer->id,
            'source' => Source::Database->value,
            'codigo' => '16002',
        ]);
    }

    public function test_add_to_quotation_toggles_it_back_off_on_a_second_click(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);

        Livewire::test(ViewPart::class, ['record' => $part->id])
            ->call('addToQuotation')
            ->call('addToQuotation')
            ->assertSet('isQuoted', false);

        $this->assertSame(0, $user->openQuotation()->items()->where('part_id', $part->id)->count());
    }

    public function test_mount_preloads_whether_the_part_is_already_in_the_open_quotation(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $user = User::factory()->create(['role' => Role::SuperAdmin]);
        $this->actingAs($user);
        $user->openQuotation()->addPart($part);

        Livewire::test(ViewPart::class, ['record' => $part->id])
            ->assertSet('isQuoted', true);
    }

    public function test_share_url_is_a_public_signed_link_anyone_can_open(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $url = Livewire::test(ViewPart::class, ['record' => $part->id])->instance()->shareUrl();

        $this->assertStringContainsString('/p/'.$part->id, $url);
        $this->assertStringContainsString('signature=', $url);

        // A URL de fato abre sem sessão nenhuma.
        $this->get($url)->assertOk();
    }

    public function test_share_qr_code_renders_valid_svg(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $svg = Livewire::test(ViewPart::class, ['record' => $part->id])->instance()->shareQrCodeSvg();

        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_share_url_is_rendered_as_a_clickable_link(): void
    {
        $manufacturer = Manufacturer::factory()->create(['is_active' => true]);
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey(), 'is_active' => true]);
        $part = Part::factory()->create(['catalog_id' => $catalog->getKey(), 'conversoes' => []]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ViewPart::class, ['record' => $part->id])
            ->assertSeeHtml('href="https://autopecascenter.local.com.br/p/'.$part->id.'?expires=');
    }
}
