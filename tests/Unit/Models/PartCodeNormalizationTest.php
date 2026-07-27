<?php

namespace Tests\Unit\Models;

use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartCodeNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_code_uppercases_and_strips_all_whitespace(): void
    {
        $this->assertSame('MG19038', Part::normalizeCode('mg 19038'));
        $this->assertSame('ABC123', Part::normalizeCode('  abc 1 2 3  '));
        $this->assertSame('GP33314', Part::normalizeCode('GP33314'));
    }

    public function test_normalize_conversoes_normalizes_leaf_values_but_keeps_brand_keys_intact(): void
    {
        $normalized = Part::normalizeConversoes([
            'nakata' => ['mg 19038', 'mg 19039'],
            'MONROE' => 'gs 440',
        ]);

        $this->assertSame([
            'nakata' => ['MG19038', 'MG19039'],
            'MONROE' => 'GS440',
        ], $normalized);
    }

    public function test_normalize_conversoes_handles_nested_arrays_and_null(): void
    {
        $normalized = Part::normalizeConversoes([
            'NAKATA' => [['codigo' => 'n 123'], 'n 456'],
        ]);

        $this->assertSame(['NAKATA' => [['codigo' => 'N123'], 'N456']], $normalized);
        $this->assertNull(Part::normalizeConversoes(null));
    }

    public function test_saving_a_part_normalizes_its_own_codigo(): void
    {
        $part = Part::factory()->create(['codigo' => 'gh 41297', 'conversoes' => []]);

        $this->assertSame('GH41297', $part->fresh()->codigo);
    }

    public function test_saving_a_part_normalizes_its_conversoes_values(): void
    {
        $part = Part::factory()->create([
            'codigo' => '16006',
            'conversoes' => ['NAKATA' => ['mg 19038', 'mg 19039']],
        ]);

        $this->assertSame(['MG19038', 'MG19039'], $part->fresh()->conversoes['NAKATA']);
    }

    public function test_updating_a_part_renormalizes_its_codigo(): void
    {
        $part = Part::factory()->create(['codigo' => '16006', 'conversoes' => []]);

        $part->update(['codigo' => 'hg 41297']);

        $this->assertSame('HG41297', $part->fresh()->codigo);
    }
}
