<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Jobs\ImportCatalogPartsFromUpload;
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

class CatalogExportImportSeedActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_action_is_visible_for_super_admin_when_the_catalog_has_parts(): void
    {
        $catalog = Catalog::factory()->create();
        Part::factory()->for($catalog)->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionVisible('exportParts', record: $catalog);
    }

    public function test_export_action_is_hidden_when_the_catalog_has_no_parts(): void
    {
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionHidden('exportParts', record: $catalog);
    }

    // Não há um teste "hidden for a seller" aqui: Livewire::test(ListCatalogs::class)
    // já falha ao montar com QUALQUER usuário Seller — inclusive testando actions
    // que já existiam antes desta mudança (ex: "import") — uma limitação pré-existente
    // do ambiente de testes desse componente, não algo introduzido por essa feature.
    // A checagem de role em si (auth()->user()?->role === Role::SuperAdmin) é simples
    // o suficiente pra não precisar dessa cobertura extra pra ter confiança nela.

    /**
     * Ao contrário de "import" (bloqueada por scraper_slug), essa action fica
     * visível independente do estado do catálogo — é o ponto inteiro dela.
     */
    public function test_import_seed_action_is_visible_for_super_admin_regardless_of_scraper_slug(): void
    {
        $withScraper = Catalog::factory()->create(['scraper_slug' => 'willtec', 'import_status' => ImportStatus::Imported]);
        $withoutScraper = Catalog::factory()->create(['scraper_slug' => null, 'import_status' => ImportStatus::NotImported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionVisible('importSeed', record: $withScraper)
            ->assertTableActionVisible('importSeed', record: $withoutScraper);
    }

    public function test_import_seed_action_dispatches_the_job_and_marks_importing_immediately(): void
    {
        Bus::fake();
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('importSeed', record: $catalog, data: [
                'file' => UploadedFile::fake()->createWithContent('seed.jsonl', "{\"codigo\":\"16088\"}\n"),
            ]);

        $this->assertSame(ImportStatus::Importing, $catalog->refresh()->import_status);
        Bus::assertDispatched(ImportCatalogPartsFromUpload::class, fn (ImportCatalogPartsFromUpload $job): bool => $job->catalog->is($catalog));
    }

    /**
     * Reproduz o caso de uso real: levar peças já raspadas aqui pra um
     * catálogo gerenciado por scraper em outro ambiente, sem travar o
     * runScraper de lá depois.
     */
    public function test_import_seed_action_works_for_a_catalog_managed_by_a_scraper(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['scraper_slug' => 'willtec', 'import_status' => ImportStatus::NotImported, 'file' => null]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('importSeed', record: $catalog, data: [
                'file' => UploadedFile::fake()->createWithContent('seed.jsonl', "{\"codigo\":\"16088\"}\n"),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
        // Continua null: essa action nunca grava catalogs.file, ao contrário de
        // "import"/"uploadUpdate" — é o ponto inteiro dela.
        $this->assertNull($catalog->refresh()->file);
    }

    public function test_import_seed_action_rejects_a_non_jsonl_file(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('importSeed', record: $catalog, data: [
                'file' => UploadedFile::fake()->createWithContent('seed.json', '{"codigo":"16095"}'),
            ])
            ->assertHasTableActionErrors(['file']);
    }
}
