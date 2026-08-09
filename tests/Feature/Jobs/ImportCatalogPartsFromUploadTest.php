<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportCatalogPartsFromUpload;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ImportCatalogPartsFromUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function putJsonl(array $lines): string
    {
        $path = 'catalogs/seed-imports/'.Str::uuid().'.jsonl';

        Storage::disk('local')->put($path, collect($lines)->map(fn (array $line) => json_encode($line))->implode("\n"));

        return $path;
    }

    public function test_imports_parts_and_activates_the_catalog(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported, 'is_active' => false]);
        $path = $this->putJsonl([['codigo' => 'ABC123', 'conversoes' => ['XYZ'], 'descricao' => 'Peça']]);

        ImportCatalogPartsFromUpload::dispatchSync($catalog, $path);
        $catalog->refresh();

        $this->assertSame(ImportStatus::Imported, $catalog->import_status);
        $this->assertTrue($catalog->is_active);
        $this->assertSame(1, $catalog->parts()->count());
    }

    /**
     * O ponto inteiro dessa funcionalidade: nunca acoplar o catálogo ao
     * arquivo enviado, ao contrário de ImportCatalogParts/ImportCatalogPartsUpdate.
     */
    public function test_never_writes_catalog_file_or_update_file(): void
    {
        $catalog = Catalog::factory()->create(['file' => null, 'update_file' => null]);
        $path = $this->putJsonl([['codigo' => 'ABC123']]);

        ImportCatalogPartsFromUpload::dispatchSync($catalog, $path);
        $catalog->refresh();

        $this->assertNull($catalog->file);
        $this->assertNull($catalog->update_file);
    }

    public function test_deletes_the_uploaded_file_after_a_successful_import(): void
    {
        $catalog = Catalog::factory()->create();
        $path = $this->putJsonl([['codigo' => 'ABC123']]);

        ImportCatalogPartsFromUpload::dispatchSync($catalog, $path);

        Storage::disk('local')->assertMissing($path);
    }

    public function test_deletes_the_uploaded_file_even_when_the_import_throws(): void
    {
        $catalog = Catalog::factory()->create();
        $path = $this->putJsonl([['codigo' => 'ABC123']]);

        Part::saving(function () {
            throw new RuntimeException('falha simulada');
        });

        try {
            ImportCatalogPartsFromUpload::dispatchSync($catalog, $path);
        } catch (RuntimeException) {
            // esperado
        }

        Storage::disk('local')->assertMissing($path);
    }

    /**
     * Ao contrário do fluxo manual existente (ImportCatalogRequest/CatalogForm),
     * essa importação não é bloqueada por o catálogo ser gerenciado por scraper.
     */
    public function test_works_for_a_catalog_with_a_scraper_slug(): void
    {
        $catalog = Catalog::factory()->create(['scraper_slug' => 'willtec']);
        $path = $this->putJsonl([['codigo' => 'ABC123']]);

        ImportCatalogPartsFromUpload::dispatchSync($catalog, $path);
        $catalog->refresh();

        $this->assertSame(ImportStatus::Imported, $catalog->import_status);
        $this->assertSame(1, $catalog->parts()->count());
    }

    public function test_is_a_no_op_when_the_file_does_not_exist(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);

        ImportCatalogPartsFromUpload::dispatchSync($catalog, 'catalogs/seed-imports/nao-existe.jsonl');
        $catalog->refresh();

        $this->assertSame(ImportStatus::NotImported, $catalog->import_status);
    }

    /**
     * Reimportar sobre um catálogo já Imported faz upsert (atualiza peças
     * existentes), não só adiciona novas — mesmo comportamento de
     * ImportCatalogParts, diferente de ImportCatalogPartsUpdate.
     */
    public function test_updates_existing_parts_on_a_catalog_that_was_already_imported(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported, 'is_active' => true]);
        Part::factory()->for($catalog)->create(['codigo' => 'ABC123', 'atributos' => ['descricao' => 'Antiga']]);

        $path = $this->putJsonl([['codigo' => 'ABC123', 'descricao' => 'Nova']]);

        ImportCatalogPartsFromUpload::dispatchSync($catalog, $path);

        $this->assertSame(1, $catalog->parts()->count());
        $this->assertSame('Nova', $catalog->parts()->first()->atributos['descricao']);
    }

    public function test_does_not_regress_status_when_an_already_imported_catalogs_upload_has_no_valid_lines(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $path = $this->putJsonl([['descricao' => 'sem código']]);

        ImportCatalogPartsFromUpload::dispatchSync($catalog, $path);
        $catalog->refresh();

        $this->assertSame(ImportStatus::Imported, $catalog->import_status);
    }
}
