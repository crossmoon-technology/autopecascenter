<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Filament\Resources\Catalogs\Pages\ViewCatalog;
use App\Models\Catalog;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogViewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_page_renders(): void
    {
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ViewCatalog::class, ['record' => $catalog->getKey()])
            ->assertSuccessful();
    }

    public function test_view_action_is_available_in_the_catalogs_table(): void
    {
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionExists('view', record: $catalog);
    }

    public function test_view_page_shows_parts_from_that_catalog(): void
    {
        $catalog = Catalog::factory()->create();
        $part = Part::factory()->create(['catalog_id' => $catalog->id, 'codigo' => '16002']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ViewCatalog::class, ['record' => $catalog->getKey()])
            ->assertSuccessful();

        $this->assertTrue($catalog->parts()->whereKey($part->id)->exists());
    }
}
