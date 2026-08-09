<?php

namespace Tests\Feature\Services\CatalogImport;

use App\Models\Catalog;
use App\Models\Part;
use App\Services\CatalogImport\ExportCatalogPartsToJsonl;
use App\Services\CatalogImport\UpsertPartsFromJsonl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportCatalogPartsToJsonlTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_one_line_per_part_with_atributos_flattened_back_out(): void
    {
        $catalog = Catalog::factory()->create();
        Part::factory()->for($catalog)->create([
            'codigo' => 'ABC123',
            'conversoes' => ['XYZ789'],
            'atributos' => ['descricao' => 'Peça de teste', 'grupo' => 'Grupo A'],
        ]);

        $jsonl = (new ExportCatalogPartsToJsonl)->execute($catalog);
        $line = json_decode($jsonl, true);

        $this->assertSame([
            'codigo' => 'ABC123',
            'conversoes' => ['XYZ789'],
            'descricao' => 'Peça de teste',
            'grupo' => 'Grupo A',
        ], $line);
    }

    public function test_omits_null_conversoes_instead_of_exporting_a_null_key(): void
    {
        $catalog = Catalog::factory()->create();
        Part::factory()->for($catalog)->create(['codigo' => 'X1', 'conversoes' => null, 'atributos' => null]);

        $line = json_decode((new ExportCatalogPartsToJsonl)->execute($catalog), true);

        $this->assertSame(['codigo' => 'X1'], $line);
    }

    public function test_exports_one_line_per_part_joined_by_newlines(): void
    {
        $catalog = Catalog::factory()->create();
        Part::factory()->for($catalog)->create(['codigo' => 'X1']);
        Part::factory()->for($catalog)->create(['codigo' => 'X2']);

        $lines = explode("\n", (new ExportCatalogPartsToJsonl)->execute($catalog));

        $this->assertCount(2, $lines);
    }

    public function test_returns_empty_string_for_a_catalog_with_no_parts(): void
    {
        $catalog = Catalog::factory()->create();

        $this->assertSame('', (new ExportCatalogPartsToJsonl)->execute($catalog));
    }

    /**
     * O ponto inteiro dessa exportação é fazer round-trip pela MESMA lógica
     * de import já usada em produção — exporta, reimporta num catálogo novo,
     * confirma que os dados batem.
     */
    public function test_round_trips_through_upsert_parts_from_jsonl(): void
    {
        $source = Catalog::factory()->create();
        Part::factory()->for($source)->create([
            'codigo' => 'ABC123',
            'conversoes' => ['XYZ789', 'DEF456'],
            'atributos' => ['descricao' => 'Peça de teste', 'grupo' => 'Grupo A'],
        ]);
        Part::factory()->for($source)->create(['codigo' => 'X2', 'conversoes' => null, 'atributos' => null]);

        $jsonl = (new ExportCatalogPartsToJsonl)->execute($source);

        $destination = Catalog::factory()->create();
        $result = (new UpsertPartsFromJsonl)->execute($destination, $jsonl);

        $this->assertSame(2, $result['imported_count']);

        $imported = Part::query()->where('catalog_id', $destination->id)->orderBy('codigo')->get();
        $this->assertSame('ABC123', $imported[0]->codigo);
        $this->assertSame(['XYZ789', 'DEF456'], $imported[0]->conversoes);
        $this->assertSame(['descricao' => 'Peça de teste', 'grupo' => 'Grupo A'], $imported[0]->atributos);
        $this->assertSame('X2', $imported[1]->codigo);
        $this->assertNull($imported[1]->conversoes);
        $this->assertNull($imported[1]->atributos);
    }
}
