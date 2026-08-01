<?php

namespace Tests\Feature\Models;

use App\Models\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_keeps_the_files_on_disk(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['file' => 'catalogs/original.jsonl', 'update_file' => 'catalogs/updates/update.jsonl']);
        Storage::disk('local')->put($catalog->file, 'conteudo');
        Storage::disk('local')->put($catalog->update_file, 'conteudo');

        $catalog->delete();

        Storage::disk('local')->assertExists($catalog->file);
        Storage::disk('local')->assertExists($catalog->update_file);
    }

    public function test_force_deleting_removes_the_files_from_disk(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['file' => 'catalogs/original.jsonl', 'update_file' => 'catalogs/updates/update.jsonl']);
        Storage::disk('local')->put($catalog->file, 'conteudo');
        Storage::disk('local')->put($catalog->update_file, 'conteudo');

        $catalog->forceDelete();

        Storage::disk('local')->assertMissing('catalogs/original.jsonl');
        Storage::disk('local')->assertMissing('catalogs/updates/update.jsonl');
    }

    public function test_force_deleting_a_catalog_without_an_update_file_does_not_error(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['file' => 'catalogs/original.jsonl', 'update_file' => null]);
        Storage::disk('local')->put($catalog->file, 'conteudo');

        $catalog->forceDelete();

        Storage::disk('local')->assertMissing('catalogs/original.jsonl');
    }

    public function test_force_deleting_a_catalog_without_any_file_does_not_error(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['file' => null, 'update_file' => null]);

        $catalog->forceDelete();

        $this->assertModelMissing($catalog);
    }
}
