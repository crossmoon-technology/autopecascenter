<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportCatalogParts;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Manufacturer;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame('MOLA A GÁS', $part->atributos['descricao']);
        $this->assertSame('PORTA MALAS', $part->atributos['posicao']);
        $this->assertSame('MOLASAGÅS', $part->atributos['categoria']);
        $this->assertSame(['GS440'], $part->conversoes['MONROE']);
        // Normalizado ao salvar (ver Part::booted()): maiúsculo, sem espaço.
        $this->assertSame(['MG16214', 'MG16215'], $part->conversoes['NAKATA']);

        $catalog->refresh();
        $this->assertTrue($catalog->is_active);
        $this->assertSame(ImportStatus::Imported, $catalog->import_status);
    }

    public function test_stores_whatever_fields_a_different_manufacturer_brings_as_generic_atributos(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false]);
        Storage::disk('local')->put($catalog->file, json_encode([
            'codigo' => 'HF-21',
            'montadora' => 'CHEVROLET',
            'veiculo' => 'A10',
            'ano' => '1986 até 1999',
            'eixo' => 'D',
        ]));

        ImportCatalogParts::dispatchSync($catalog);

        $part = Part::query()->where('codigo', 'HF-21')->firstOrFail();
        $this->assertSame('CHEVROLET', $part->atributos['montadora']);
        $this->assertSame('A10', $part->atributos['veiculo']);
        $this->assertSame('1986 até 1999', $part->atributos['ano']);
        $this->assertSame('D', $part->atributos['eixo']);
        $this->assertArrayNotHasKey('descricao', $part->atributos);
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
        $this->assertSame('Descrição nova', Part::query()->where('codigo', '16088')->firstOrFail()->atributos['descricao']);
    }

    public function test_reimporting_with_different_casing_or_spacing_updates_the_same_part(): void
    {
        // O WHERE do updateOrCreate precisa normalizar ANTES de comparar — senão "gh 123"
        // (bruto do jsonl) não bate com "GH123" (já salvo, normalizado), e o job tenta
        // criar uma peça nova que colide com o unique(catalog_id, codigo) no insert.
        Storage::fake('local');
        $catalog = Catalog::factory()->create();
        Storage::disk('local')->put($catalog->file, json_encode(['codigo' => 'GH 123', 'descricao' => 'Primeira']));
        ImportCatalogParts::dispatchSync($catalog);

        Storage::disk('local')->put($catalog->file, json_encode(['codigo' => 'gh123', 'descricao' => 'Segunda']));
        ImportCatalogParts::dispatchSync($catalog);

        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
        $part = Part::query()->where('codigo', 'GH123')->firstOrFail();
        $this->assertSame('Segunda', $part->atributos['descricao']);
    }

    public function test_indexes_reference_codes_for_the_imported_parts(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false]);
        Storage::disk('local')->put($catalog->file, json_encode([
            'codigo' => '16088',
            'conversoes' => ['NAKATA' => ['MG 16214', 'MG 16215']],
        ]));

        ImportCatalogParts::dispatchSync($catalog);

        $part = Part::query()->where('codigo', '16088')->firstOrFail();
        $tokens = DB::table('part_reference_codes')->where('part_id', $part->id)->pluck('token');

        $this->assertTrue($tokens->contains('16088'));
        $this->assertTrue($tokens->contains('MG16214'));
        $this->assertTrue($tokens->contains('MG16215'));
    }

    public function test_links_two_parts_that_share_a_code_across_catalogs(): void
    {
        Storage::fake('local');

        // A peça da Nakata precisa já ter passado pela importação (e portanto pelo
        // índice) ANTES — o pareamento só encontra peças que já foram indexadas, não
        // varre o banco inteiro a cada import.
        $nakataCatalog = Catalog::factory()->create(['manufacturer_id' => Manufacturer::factory(), 'is_active' => false]);
        Storage::disk('local')->put($nakataCatalog->file, json_encode(['codigo' => 'MG 16214', 'conversoes' => []]));
        ImportCatalogParts::dispatchSync($nakataCatalog);

        $cofapCatalog = Catalog::factory()->create(['is_active' => false]);
        Storage::disk('local')->put($cofapCatalog->file, json_encode([
            'codigo' => '16088',
            'conversoes' => ['NAKATA' => ['MG 16214']],
        ]));
        ImportCatalogParts::dispatchSync($cofapCatalog);

        $cofapPart = Part::query()->where('codigo', '16088')->firstOrFail();
        // Normalizado ao salvar (ver Part::booted()): maiúsculo, sem espaço.
        $nakataPart = Part::query()->where('codigo', 'MG16214')->firstOrFail();

        $this->assertTrue($cofapPart->equivalentParts()->whereKey($nakataPart->id)->exists());
        $this->assertTrue($nakataPart->equivalentParts()->whereKey($cofapPart->id)->exists());
    }

    public function test_skips_a_line_that_collides_with_a_soft_deleted_orphan_part(): void
    {
        // Reproduz um catálogo que foi soft-deletado (Catalog::booted() soft-deleta
        // as peças junto) e depois restaurado sem que as peças fossem restauradas —
        // o updateOrCreate não enxerga a peça soft-deletada (fora do escopo padrão),
        // então tenta um INSERT que colide com o unique(catalog_id, codigo) de verdade.
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['is_active' => false]);
        $orphan = Part::factory()->for($catalog)->create(['codigo' => 'X1']);
        $orphan->delete();

        Storage::disk('local')->put($catalog->file, implode("\n", [
            json_encode(['codigo' => 'X1', 'descricao' => 'Não deveria sobrescrever a órfã']),
            json_encode(['codigo' => 'X2', 'descricao' => 'Peça nova']),
        ]));

        ImportCatalogParts::dispatchSync($catalog);

        $catalog->refresh();
        $this->assertTrue($catalog->is_active);
        $this->assertSame(ImportStatus::Imported, $catalog->import_status);
        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
        $this->assertNotNull(Part::query()->where('codigo', 'X2')->first());
        $this->assertTrue(Part::onlyTrashed()->whereKey($orphan->id)->exists());
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
