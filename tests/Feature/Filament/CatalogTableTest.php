<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Jobs\ImportCatalogParts;
use App\Jobs\ImportCatalogPartsUpdate;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_action_imports_the_catalog_file_and_activates_it(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false]);
        Storage::disk('local')->put($catalog->file, json_encode([
            'codigo' => '16088',
            'descricao' => 'MOLA A GÁS',
        ]));
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionExists('import', record: $catalog)
            ->callTableAction('import', record: $catalog);

        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
        $this->assertTrue($catalog->refresh()->is_active);
    }

    public function test_import_action_marks_the_catalog_as_importing_immediately(): void
    {
        Bus::fake();
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('import', record: $catalog);

        $this->assertSame(ImportStatus::Importing, $catalog->refresh()->import_status);
        Bus::assertDispatched(ImportCatalogParts::class);
    }

    public function test_upload_update_action_is_only_visible_for_an_already_imported_catalog(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $imported = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $notImported = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);

        Livewire::test(ListCatalogs::class)
            ->assertTableActionVisible('uploadUpdate', record: $imported)
            ->assertTableActionHidden('uploadUpdate', record: $notImported);
    }

    public function test_upload_update_action_creates_only_the_new_parts(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        Part::factory()->create(['catalog_id' => $catalog->id, 'codigo' => '16088']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('uploadUpdate', record: $catalog, data: [
                'file' => UploadedFile::fake()->createWithContent(
                    'update.jsonl',
                    "{\"codigo\":\"16088\"}\n{\"codigo\":\"16093\"}\n"
                ),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, Part::query()->where('catalog_id', $catalog->id)->count());
        $this->assertSame(ImportStatus::Imported, $catalog->refresh()->import_status);
    }

    public function test_upload_update_action_dispatches_the_update_job(): void
    {
        Bus::fake();
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('uploadUpdate', record: $catalog, data: [
                'file' => UploadedFile::fake()->createWithContent('update.jsonl', "{\"codigo\":\"16094\"}\n"),
            ]);

        Bus::assertDispatched(ImportCatalogPartsUpdate::class, fn (ImportCatalogPartsUpdate $job): bool => $job->catalog->is($catalog));
    }

    public function test_upload_update_action_rejects_a_non_jsonl_file(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('uploadUpdate', record: $catalog, data: [
                'file' => UploadedFile::fake()->createWithContent('update.json', '{"codigo":"16095"}'),
            ])
            ->assertHasTableActionErrors(['file']);
    }
}
