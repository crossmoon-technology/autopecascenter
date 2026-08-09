<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\CreateCatalog;
use App\Filament\Resources\Catalogs\Pages\EditCatalog;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\User;
use App\Services\CatalogScraping\Scrapers\C123CatalogScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogScraperSlugFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scrapers' => [
            ['name' => 'Willtec', 'slug' => 'willtec', 'url' => 'https://c123.com.br/willtec', 'class' => C123CatalogScraper::class],
        ]]);
    }

    public function test_accepts_a_catalog_with_a_configured_scraper_and_no_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo via scraper',
                'scraper_slug' => 'willtec',
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('willtec', Catalog::query()->latest('id')->first()->scraper_slug);
    }

    public function test_file_field_is_disabled_once_a_scraper_is_selected(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->assertFormFieldEnabled('file')
            ->fillForm(['scraper_slug' => 'willtec'])
            ->assertFormFieldDisabled('file');
    }

    public function test_file_field_is_disabled_on_edit_when_the_catalog_already_has_a_scraper(): void
    {
        $catalog = Catalog::factory()->create(['scraper_slug' => 'willtec']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->assertFormFieldDisabled('file');
    }

    /**
     * Um provedor de scraping só pode alimentar um catálogo por vez — senão o
     * ScrapeCatalogs reimportaria a mesma fonte pra dois catálogos diferentes.
     */
    public function test_rejects_a_scraper_already_used_by_another_catalog(): void
    {
        Catalog::factory()->create(['scraper_slug' => 'willtec']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Outro catálogo',
                'scraper_slug' => 'willtec',
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasFormErrors(['scraper_slug']);
    }

    public function test_allows_editing_a_catalog_while_keeping_its_own_scraper(): void
    {
        $catalog = Catalog::factory()->create(['scraper_slug' => 'willtec']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->fillForm(['scraper_slug' => 'willtec'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_scraper_field_is_disabled_once_a_file_is_uploaded(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->assertFormFieldEnabled('scraper_slug')
            ->fillForm(['file' => UploadedFile::fake()->createWithContent('catalog.jsonl', "{\"codigo\":\"A1\"}\n")])
            ->assertFormFieldDisabled('scraper_slug');
    }

    /**
     * Ambos os campos ficam desabilitados reativamente quando o outro é
     * preenchido, mas a regra de validação é a trava de verdade — protege
     * contra qualquer jeito de submeter os dois preenchidos ao mesmo tempo.
     */
    public function test_rejects_submitting_both_a_file_and_a_scraper(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo conflitante',
                'scraper_slug' => 'willtec',
                'file' => UploadedFile::fake()->createWithContent('catalog.jsonl', "{\"codigo\":\"A1\"}\n"),
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasFormErrors(['scraper_slug', 'file']);
    }
}
