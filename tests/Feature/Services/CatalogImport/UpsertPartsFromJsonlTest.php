<?php

namespace Tests\Feature\Services\CatalogImport;

use App\Models\Catalog;
use App\Models\Part;
use App\Services\CatalogImport\UpsertPartsFromJsonl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertPartsFromJsonlTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_part_from_a_valid_line(): void
    {
        $catalog = Catalog::factory()->create();

        $result = (new UpsertPartsFromJsonl)->execute($catalog, json_encode([
            'codigo' => 'ABC123',
            'conversoes' => ['XYZ789'],
            'descricao' => 'Peça de teste',
        ]));

        $this->assertSame(1, $result['imported_count']);

        $part = Part::query()->where('catalog_id', $catalog->id)->first();
        $this->assertSame('ABC123', $part->codigo);
        $this->assertSame(['XYZ789'], $part->conversoes);
        $this->assertSame(['descricao' => 'Peça de teste'], $part->atributos);
        $this->assertSame([$part->id], $result['imported_part_ids']);
    }

    public function test_ignores_a_malformed_line(): void
    {
        $catalog = Catalog::factory()->create();

        $result = (new UpsertPartsFromJsonl)->execute($catalog, "não é json\n".json_encode(['codigo' => 'X1']));

        $this->assertSame(1, $result['imported_count']);
        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
    }

    public function test_ignores_a_line_without_a_codigo(): void
    {
        $catalog = Catalog::factory()->create();

        $result = (new UpsertPartsFromJsonl)->execute($catalog, json_encode(['descricao' => 'sem código']));

        $this->assertSame(0, $result['imported_count']);
    }

    public function test_updates_an_existing_part_instead_of_duplicating(): void
    {
        $catalog = Catalog::factory()->create();
        Part::factory()->for($catalog)->create(['codigo' => 'ABC123', 'atributos' => ['descricao' => 'Antiga']]);

        $result = (new UpsertPartsFromJsonl)->execute($catalog, json_encode(['codigo' => 'abc 123', 'descricao' => 'Nova']));

        $this->assertSame(1, $result['imported_count']);
        $this->assertSame(1, Part::query()->where('catalog_id', $catalog->id)->count());
        $this->assertSame('Nova', Part::query()->where('catalog_id', $catalog->id)->first()->atributos['descricao']);
    }

    /**
     * Reproduz uma peça órfã soft-deletada (ex: catálogo soft-deletado e
     * restaurado sem restaurar as peças junto — ver Catalog::booted()) —
     * a linha correspondente é ignorada em vez de derrubar a importação
     * inteira.
     */
    public function test_skips_a_line_that_collides_with_a_soft_deleted_orphan_part(): void
    {
        $catalog = Catalog::factory()->create();
        $orphan = Part::factory()->for($catalog)->create(['codigo' => 'X1']);
        $orphan->delete();

        $result = (new UpsertPartsFromJsonl)->execute($catalog, json_encode(['codigo' => 'X1'])."\n".json_encode(['codigo' => 'X2']));

        $this->assertSame(1, $result['imported_count']);
        $this->assertTrue(Part::onlyTrashed()->whereKey($orphan->id)->exists());
    }

    public function test_returns_zero_for_empty_content(): void
    {
        $catalog = Catalog::factory()->create();

        $result = (new UpsertPartsFromJsonl)->execute($catalog, '');

        $this->assertSame(0, $result['imported_count']);
        $this->assertSame([], $result['imported_part_ids']);
    }
}
