<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportCatalogParts;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCatalogPartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_parts_from_the_catalog_jsonl_file_and_activates_it(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false]);
        Storage::disk('local')->put($catalog->file, implode("\n", [
            json_encode([
                'codigo' => '16088',
                'descricao' => 'MOLA A GÁS',
                'tipo' => 'MOLA A GÁS',
                'posicao' => 'PORTA MALAS',
                'conversoes' => [
                    'MONROE' => ['GS440'],
                    'NAKATA' => ['MG 16214', 'MG 16215'],
                ],
                'categoria' => 'MOLASAGÅS',
            ]),
            json_encode([
                'codigo' => '16089',
                'descricao' => 'AMORTECEDOR',
            ]),
            '', // blank line, should be skipped
            'not valid json', // malformed line, should be skipped
            json_encode(['descricao' => 'sem código']), // missing codigo, should be skipped
        ]));

        ImportCatalogParts::dispatchSync($catalog);

        $this->assertSame(2, Part::query()->where('catalog_id', $catalog->id)->count());

        $part = Part::query()->where('codigo', '16088')->firstOrFail();
        $this->assertSame('MOLA A GÁS', $part->descricao);
        $this->assertSame('PORTA MALAS', $part->posicao);
        $this->assertSame('MOLASAGÅS', $part->categoria);
        $this->assertSame(['GS440'], $part->conversoes['MONROE']);
        $this->assertSame(['MG 16214', 'MG 16215'], $part->conversoes['NAKATA']);

        $catalog->refresh();
        $this->assertTrue($catalog->is_active);
        $this->assertSame(ImportStatus::Imported, $catalog->import_status);
    }

    public function test_reimporting_updates_existing_parts_instead_of_duplicating(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create();
        Storage::disk('local')->put($catalog->file, json_encode([
            'codigo' => '16088',
            'descricao' => 'Descrição antiga',
        ]));
        ImportCatalogParts::dispatchSync($catalog);

        Storage::disk('local')->put($catalog->file, json_encode([
            'codigo' => '16088',
            'descricao' => 'Descrição nova',
        ]));
        ImportCatalogParts::dispatchSync($catalog);

        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
        $this->assertSame('Descrição nova', Part::query()->where('codigo', '16088')->firstOrFail()->descricao);
    }

    public function test_does_not_activate_the_catalog_when_nothing_could_be_imported(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false]);
        Storage::disk('local')->put($catalog->file, "not valid json\n");

        ImportCatalogParts::dispatchSync($catalog);

        $catalog->refresh();
        $this->assertFalse($catalog->is_active);
        $this->assertSame(ImportStatus::NotImported, $catalog->import_status);
    }
}
