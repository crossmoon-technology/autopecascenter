<?php

namespace Tests\Feature\Services\PartEquivalence;

use App\Models\Part;
use App\Services\PartEquivalence\RebuildPartEquivalences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RebuildPartEquivalencesTest extends TestCase
{
    use RefreshDatabase;

    private function rebuild(Part ...$parts): void
    {
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereIn('id', collect($parts)->pluck('id'))->get());
    }

    public function test_indexes_the_parts_own_codigo_and_conversoes_as_reference_codes(): void
    {
        $part = Part::factory()->create([
            'codigo' => '16088',
            'conversoes' => ['NAKATA' => ['MG 16214', 'MG 16215'], 'MONROE' => 'GS440'],
        ]);

        $this->rebuild($part);

        $tokens = DB::table('part_reference_codes')->where('part_id', $part->id)->pluck('token')->sort()->values();

        $this->assertSame(['16088', 'GS440', 'MG16214', 'MG16215'], $tokens->all());
    }

    public function test_tokens_are_normalized_to_uppercase_with_no_spaces(): void
    {
        $part = Part::factory()->create(['codigo' => '  abc 123  ', 'conversoes' => []]);

        $this->rebuild($part);

        $this->assertTrue(
            DB::table('part_reference_codes')->where('part_id', $part->id)->where('token', 'ABC123')->exists()
        );
    }

    public function test_links_two_parts_that_share_a_token_in_both_directions(): void
    {
        $a = Part::factory()->create(['codigo' => '16088', 'conversoes' => ['NAKATA' => ['MG 16214']]]);
        $b = Part::factory()->create(['codigo' => 'MG 16214', 'conversoes' => []]);

        $this->rebuild($a, $b);

        $this->assertTrue($a->equivalentParts()->whereKey($b->id)->exists());
        $this->assertTrue($b->equivalentParts()->whereKey($a->id)->exists());
    }

    public function test_does_not_link_parts_that_share_no_token(): void
    {
        $a = Part::factory()->create(['codigo' => 'A1', 'conversoes' => []]);
        $b = Part::factory()->create(['codigo' => 'B2', 'conversoes' => []]);

        $this->rebuild($a, $b);

        $this->assertFalse($a->equivalentParts()->whereKey($b->id)->exists());
    }

    public function test_pairing_is_direct_not_transitive(): void
    {
        // A e B compartilham "X123"; B e C compartilham "Y456" — mas A e C não têm
        // nenhum token em comum entre si, então não devem virar par um do outro.
        $a = Part::factory()->create(['codigo' => 'A1', 'conversoes' => ['X' => ['X123']]]);
        $b = Part::factory()->create(['codigo' => 'X123', 'conversoes' => ['Y' => ['Y456']]]);
        $c = Part::factory()->create(['codigo' => 'Y456', 'conversoes' => []]);

        $this->rebuild($a, $b, $c);

        $this->assertTrue($a->equivalentParts()->whereKey($b->id)->exists());
        $this->assertTrue($b->equivalentParts()->whereKey($c->id)->exists());
        $this->assertFalse($a->equivalentParts()->whereKey($c->id)->exists());
        $this->assertFalse($c->equivalentParts()->whereKey($a->id)->exists());
    }

    public function test_rerunning_for_the_same_parts_does_not_duplicate_pairs(): void
    {
        $a = Part::factory()->create(['codigo' => '16088', 'conversoes' => ['NAKATA' => ['MG 16214']]]);
        $b = Part::factory()->create(['codigo' => 'MG 16214', 'conversoes' => []]);

        $this->rebuild($a, $b);
        $this->rebuild($a, $b);
        $this->rebuild($a, $b);

        $this->assertSame(1, DB::table('part_equivalences')->where('part_id', $a->id)->where('equivalent_part_id', $b->id)->count());
    }

    public function test_removes_stale_pairs_when_a_parts_codigo_changes(): void
    {
        $a = Part::factory()->create(['codigo' => '16088', 'conversoes' => ['NAKATA' => ['MG 16214']]]);
        $b = Part::factory()->create(['codigo' => 'MG 16214', 'conversoes' => []]);
        $this->rebuild($a, $b);
        $this->assertTrue($a->fresh()->equivalentParts()->whereKey($b->id)->exists());

        $a->update(['conversoes' => ['NAKATA' => ['SOMETHING-ELSE']]]);
        $this->rebuild($a);

        $this->assertFalse($a->fresh()->equivalentParts()->whereKey($b->id)->exists());
        $this->assertFalse($b->fresh()->equivalentParts()->whereKey($a->id)->exists());
    }

    public function test_ignores_a_token_shared_by_too_many_parts(): void
    {
        $parts = Part::factory()->count(30)->create(['codigo' => fn () => fake()->unique()->uuid(), 'conversoes' => ['GENERIC' => ['NOISY-TOKEN']]]);

        $this->rebuild(...$parts->all());

        $first = $parts->first();
        $this->assertSame(0, DB::table('part_equivalences')->where('part_id', $first->id)->count());
    }

    public function test_handles_nested_array_conversoes_without_error(): void
    {
        $part = Part::factory()->create([
            'codigo' => 'SP-9',
            'conversoes' => ['NAKATA' => [['codigo' => 'N123'], 'N456']],
        ]);

        $this->rebuild($part);

        $tokens = DB::table('part_reference_codes')->where('part_id', $part->id)->pluck('token')->sort()->values();
        $this->assertSame(['N123', 'N456', 'SP-9'], $tokens->all());
    }

    public function test_does_nothing_for_an_empty_collection(): void
    {
        app(RebuildPartEquivalences::class)->forParts(Part::query()->whereRaw('1 = 0')->get());

        $this->assertSame(0, DB::table('part_reference_codes')->count());
        $this->assertSame(0, DB::table('part_equivalences')->count());
    }

    public function test_deleting_a_part_cascades_to_its_index_rows(): void
    {
        $a = Part::factory()->create(['codigo' => '16088', 'conversoes' => ['NAKATA' => ['MG 16214']]]);
        $b = Part::factory()->create(['codigo' => 'MG 16214', 'conversoes' => []]);
        $this->rebuild($a, $b);

        $a->forceDelete();

        $this->assertSame(0, DB::table('part_reference_codes')->where('part_id', $a->id)->count());
        $this->assertSame(0, DB::table('part_equivalences')->where('part_id', $a->id)->orWhere('equivalent_part_id', $a->id)->count());
    }
}
