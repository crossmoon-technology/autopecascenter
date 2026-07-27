<?php

namespace Tests\Feature\Console;

use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RebuildPartEquivalencesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_reference_codes_and_pairs_for_parts_created_outside_the_import_pipeline(): void
    {
        // Simula peças que já existiam antes dessa funcionalidade — criadas direto via
        // factory, nunca passaram por ImportCatalogParts/ImportCatalogPartsUpdate.
        $a = Part::factory()->create(['codigo' => '16088', 'conversoes' => ['NAKATA' => ['MG 16214']]]);
        $b = Part::factory()->create(['codigo' => 'MG 16214', 'conversoes' => []]);

        $this->assertSame(0, DB::table('part_reference_codes')->count());

        $this->artisan('parts:rebuild-equivalences')->assertSuccessful();

        $this->assertTrue(DB::table('part_reference_codes')->where('part_id', $a->id)->where('token', '16088')->exists());
        $this->assertTrue($a->fresh()->equivalentParts()->whereKey($b->id)->exists());
    }
}
