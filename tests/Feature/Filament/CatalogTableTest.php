<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Jobs\ImportCatalogParts;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
