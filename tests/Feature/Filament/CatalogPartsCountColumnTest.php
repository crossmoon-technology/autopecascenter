<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogPartsCountColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_zero_for_a_catalog_that_was_never_imported(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableColumnStateSet('parts_count', 0, record: $catalog);
    }

    public function test_counts_the_parts_of_an_imported_catalog(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        Part::factory()->count(3)->create(['catalog_id' => $catalog->id]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableColumnStateSet('parts_count', 3, record: $catalog);
    }

    public function test_does_not_count_soft_deleted_parts(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        Part::factory()->count(2)->create(['catalog_id' => $catalog->id]);
        Part::factory()->create(['catalog_id' => $catalog->id])->delete();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableColumnStateSet('parts_count', 2, record: $catalog);
    }
}
