<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\CatalogImportController\Exceptions\CatalogNotImportedException;
use App\Jobs\ImportCatalogPartsUpdate;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Informativo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test-catalog-import-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.catalog_import.api_key' => self::API_KEY]);
    }

    private function jsonlUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'update.jsonl',
            implode("\n", [
                json_encode(['codigo' => 'A1', 'descricao' => 'Peça nova']),
                json_encode(['codigo' => 'A2', 'descricao' => 'Outra peça nova']),
            ])
        );
    }

    public function test_rejects_a_request_without_an_api_key(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $response = $this->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => $this->jsonlUpload(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_rejects_a_request_with_the_wrong_api_key(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $response = $this->withHeader('X-Api-Key', 'wrong-key')->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => $this->jsonlUpload(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_rejects_an_unknown_slug(): void
    {
        $response = $this->withHeader('X-Api-Key', self::API_KEY)->postJson('/api/catalogs/import', [
            'slug' => 'does-not-exist',
            'file' => $this->jsonlUpload(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['slug']);
    }

    public function test_rejects_a_catalog_that_has_not_been_imported_yet(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);

        $response = $this->withHeader('X-Api-Key', self::API_KEY)->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => $this->jsonlUpload(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['slug']);
        $this->assertSame(
            CatalogNotImportedException::MESSAGE,
            $response->json('errors.slug.0')
        );
    }

    public function test_rejects_a_non_jsonl_file(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $response = $this->withHeader('X-Api-Key', self::API_KEY)->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => UploadedFile::fake()->createWithContent('update.json', json_encode(['codigo' => 'A1'])),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    public function test_queues_the_update_job_with_the_stored_file(): void
    {
        Storage::fake('local');
        Queue::fake();
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $response = $this->withHeader('X-Api-Key', self::API_KEY)->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => $this->jsonlUpload(),
        ]);

        $response->assertAccepted();

        Queue::assertPushed(ImportCatalogPartsUpdate::class, function (ImportCatalogPartsUpdate $job) use ($catalog) {
            return $job->catalog->is($catalog) && Storage::disk('local')->exists($job->file);
        });
    }

    public function test_marks_the_catalog_as_importing_immediately(): void
    {
        Storage::fake('local');
        Queue::fake();
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $this->withHeader('X-Api-Key', self::API_KEY)->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => $this->jsonlUpload(),
        ]);

        $this->assertSame(ImportStatus::Importing, $catalog->refresh()->import_status);
    }

    public function test_creates_informativos_sent_alongside_the_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $response = $this->withHeader('X-Api-Key', self::API_KEY)->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => $this->jsonlUpload(),
            'informativos' => [
                UploadedFile::fake()->create('boletim.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('capa.png'),
            ],
        ]);

        $response->assertAccepted();
        $this->assertSame(2, Informativo::query()->where('catalog_id', $catalog->id)->count());

        $pdf = Informativo::query()->where('catalog_id', $catalog->id)->where('original_name', 'boletim.pdf')->firstOrFail();
        Storage::disk('public')->assertExists($pdf->file);
    }

    public function test_works_without_any_informativos(): void
    {
        Storage::fake('local');
        Queue::fake();
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $response = $this->withHeader('X-Api-Key', self::API_KEY)->postJson('/api/catalogs/import', [
            'slug' => $catalog->slug,
            'file' => $this->jsonlUpload(),
        ]);

        $response->assertAccepted();
        $this->assertSame(0, Informativo::query()->where('catalog_id', $catalog->id)->count());
    }
}
