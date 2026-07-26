<?php

namespace Tests\Feature\Filament;

use App\Models\Catalog;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSoftDeleteCascadesPartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_a_catalog_also_soft_deletes_its_parts(): void
    {
        $catalog = Catalog::factory()->create();
        $parts = Part::factory()->count(3)->create(['catalog_id' => $catalog->id]);

        $catalog->delete();

        $this->assertSame(0, Part::query()->whereIn('id', $parts->pluck('id'))->count());
        $this->assertSame(3, Part::withTrashed()->whereIn('id', $parts->pluck('id'))->count());
        $this->assertNotNull($catalog->fresh()->deleted_at);
        foreach ($parts as $part) {
            $this->assertNotNull($part->fresh()->deleted_at);
        }
    }

    public function test_force_deleting_a_catalog_permanently_removes_its_parts(): void
    {
        $catalog = Catalog::factory()->create();
        $parts = Part::factory()->count(3)->create(['catalog_id' => $catalog->id]);

        $catalog->forceDelete();

        $this->assertSame(0, Part::withTrashed()->whereIn('id', $parts->pluck('id'))->count());
    }
}
