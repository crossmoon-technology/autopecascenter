<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportCatalogPartsUpdate;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCatalogPartsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_only_the_parts_with_a_new_codigo(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        Part::factory()->create([
            'catalog_id' => $catalog->id,
            'codigo' => '16088',
            'atributos' => ['descricao' => 'Descrição original'],
        ]);

        $updateFile = 'catalogs/updates/update.jsonl';
        Storage::disk('local')->put($updateFile, implode("\n", [
            json_encode(['codigo' => '16088', 'descricao' => 'Descrição nova, deveria ser ignorada']),
            json_encode(['codigo' => '16090', 'descricao' => 'Peça nova']),
        ]));

        ImportCatalogPartsUpdate::dispatchSync($catalog, $updateFile);

        $this->assertSame(2, Part::query()->where('catalog_id', $catalog->id)->count());

        $existing = Part::query()->where('catalog_id', $catalog->id)->where('codigo', '16088')->firstOrFail();
        $this->assertSame('Descrição original', $existing->atributos['descricao']);

        $new = Part::query()->where('catalog_id', $catalog->id)->where('codigo', '16090')->firstOrFail();
        $this->assertSame('Peça nova', $new->atributos['descricao']);
    }

    public function test_ignores_blank_and_malformed_lines(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $updateFile = 'catalogs/updates/update.jsonl';
        Storage::disk('local')->put($updateFile, implode("\n", [
            json_encode(['codigo' => '16091']),
            '',
            'not valid json',
            json_encode(['descricao' => 'sem código']),
        ]));

        ImportCatalogPartsUpdate::dispatchSync($catalog, $updateFile);

        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
    }

    public function test_indexes_reference_codes_only_for_the_newly_created_parts(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $existing = Part::factory()->create([
            'catalog_id' => $catalog->id,
            'codigo' => '16088',
            'conversoes' => [],
        ]);

        $updateFile = 'catalogs/updates/update.jsonl';
        Storage::disk('local')->put($updateFile, implode("\n", [
            json_encode(['codigo' => '16088', 'descricao' => 'Ignorado, código já existe']),
            json_encode(['codigo' => '16090', 'conversoes' => ['NAKATA' => ['MG 90000']]]),
        ]));

        ImportCatalogPartsUpdate::dispatchSync($catalog, $updateFile);

        // A peça já existente não foi tocada pelo firstOrCreate — não deve ganhar um
        // índice novo (se já tinha algum, continua o mesmo de antes; aqui nunca teve).
        $this->assertSame(0, DB::table('part_reference_codes')->where('part_id', $existing->id)->count());

        $newPart = Part::query()->where('codigo', '16090')->firstOrFail();
        $tokens = DB::table('part_reference_codes')->where('part_id', $newPart->id)->pluck('token');
        $this->assertTrue($tokens->contains('16090'));
        $this->assertTrue($tokens->contains('MG90000'));
    }

    public function test_leaves_the_catalog_imported_when_done(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $updateFile = 'catalogs/updates/update.jsonl';
        Storage::disk('local')->put($updateFile, json_encode(['codigo' => '16092']));

        ImportCatalogPartsUpdate::dispatchSync($catalog, $updateFile);

        $this->assertSame(ImportStatus::Imported, $catalog->refresh()->import_status);
    }

    public function test_records_the_processed_file_as_the_catalogs_update_file(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $updateFile = 'catalogs/updates/update.jsonl';
        Storage::disk('local')->put($updateFile, json_encode(['codigo' => '16092']));

        ImportCatalogPartsUpdate::dispatchSync($catalog, $updateFile);

        $this->assertSame($updateFile, $catalog->refresh()->update_file);
        Storage::disk('local')->assertExists($updateFile);
    }

    public function test_a_new_update_deletes_the_previous_update_file(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        $firstUpdateFile = 'catalogs/updates/first.jsonl';
        Storage::disk('local')->put($firstUpdateFile, json_encode(['codigo' => '16092']));
        ImportCatalogPartsUpdate::dispatchSync($catalog, $firstUpdateFile);
        $this->assertSame($firstUpdateFile, $catalog->refresh()->update_file);

        $secondUpdateFile = 'catalogs/updates/second.jsonl';
        Storage::disk('local')->put($secondUpdateFile, json_encode(['codigo' => '16093']));
        ImportCatalogPartsUpdate::dispatchSync($catalog, $secondUpdateFile);

        $this->assertSame($secondUpdateFile, $catalog->refresh()->update_file);
        Storage::disk('local')->assertMissing($firstUpdateFile);
        Storage::disk('local')->assertExists($secondUpdateFile);
    }

    public function test_a_missing_update_file_does_not_overwrite_the_previous_update_file_reference(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create([
            'import_status' => ImportStatus::Imported,
            'update_file' => 'catalogs/updates/previous.jsonl',
        ]);
        Storage::disk('local')->put('catalogs/updates/previous.jsonl', json_encode(['codigo' => '16092']));

        ImportCatalogPartsUpdate::dispatchSync($catalog, 'catalogs/updates/does-not-exist.jsonl');

        $this->assertSame('catalogs/updates/previous.jsonl', $catalog->refresh()->update_file);
        Storage::disk('local')->assertExists('catalogs/updates/previous.jsonl');
    }

    public function test_does_not_affect_parts_from_a_different_catalog_with_the_same_codigo(): void
    {
        Storage::fake('local');
        $catalogA = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $catalogB = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        Part::factory()->create([
            'catalog_id' => $catalogB->id,
            'codigo' => '16088',
            'atributos' => ['descricao' => 'Peça do outro catálogo'],
        ]);

        $updateFile = 'catalogs/updates/update.jsonl';
        Storage::disk('local')->put($updateFile, json_encode(['codigo' => '16088', 'descricao' => 'Peça nova']));

        ImportCatalogPartsUpdate::dispatchSync($catalogA, $updateFile);

        $this->assertSame(1, Part::query()->where('catalog_id', $catalogA->id)->count());
        $this->assertSame(1, Part::query()->where('catalog_id', $catalogB->id)->count());
        $this->assertSame(
            'Peça do outro catálogo',
            Part::query()->where('catalog_id', $catalogB->id)->firstOrFail()->atributos['descricao']
        );
    }

    public function test_handles_a_missing_update_file_gracefully(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);

        ImportCatalogPartsUpdate::dispatchSync($catalog, 'catalogs/updates/does-not-exist.jsonl');

        $this->assertSame(0, Part::query()->where('catalog_id', $catalog->id)->count());
        $this->assertSame(ImportStatus::Imported, $catalog->refresh()->import_status);
    }
}
