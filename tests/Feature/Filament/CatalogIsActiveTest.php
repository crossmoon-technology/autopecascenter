<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\CreateCatalog;
use App\Filament\Resources\Catalogs\Pages\EditCatalog;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogIsActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_active_cannot_be_set_to_true_on_create(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo 2026',
                'file' => UploadedFile::fake()->createWithContent('catalog.jsonl', "{\"code\":\"A1\"}\n"),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Catalog::firstOrFail()->is_active);
    }

    public function test_is_active_can_be_set_to_true_on_edit_once_imported(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false, 'import_status' => ImportStatus::Imported]);
        Storage::disk('local')->put($catalog->file, '{"code":"A1"}');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($catalog->refresh()->is_active);
    }

    public function test_is_active_stays_disabled_when_import_has_not_run_yet(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false, 'import_status' => ImportStatus::NotImported]);
        Storage::disk('local')->put($catalog->file, '{"code":"A1"}');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($catalog->refresh()->is_active);
    }

    public function test_is_active_stays_disabled_while_importing(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false, 'import_status' => ImportStatus::Importing]);
        Storage::disk('local')->put($catalog->file, '{"code":"A1"}');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($catalog->refresh()->is_active);
    }

    /**
     * Um catálogo com provedor de scraping não depende do botão manual de
     * "Importar" — liberamos o toggle mesmo antes da 1ª execução automática.
     */
    public function test_is_active_can_be_set_to_true_on_create_when_a_scraper_is_selected(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo via scraper',
                'scraper_slug' => 'willtec',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Catalog::firstOrFail()->is_active);
    }

    public function test_is_active_can_be_toggled_on_edit_before_import_when_a_scraper_is_selected(): void
    {
        $catalog = Catalog::factory()->create([
            'is_active' => false,
            'import_status' => ImportStatus::NotImported,
            'scraper_slug' => 'willtec',
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($catalog->refresh()->is_active);
    }
}
