<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogIsActiveToggleColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_activates_a_catalog_once_it_has_been_imported(): void
    {
        $catalog = Catalog::factory()->create(['is_active' => false, 'import_status' => ImportStatus::Imported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->call('updateTableColumnState', 'is_active', (string) $catalog->getKey(), true);

        $this->assertTrue($catalog->refresh()->is_active);
    }

    public function test_toggle_does_nothing_when_the_import_has_not_run_yet(): void
    {
        $catalog = Catalog::factory()->create(['is_active' => false, 'import_status' => ImportStatus::NotImported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->call('updateTableColumnState', 'is_active', (string) $catalog->getKey(), true);

        $this->assertFalse($catalog->refresh()->is_active);
    }

    public function test_toggle_does_nothing_while_importing(): void
    {
        $catalog = Catalog::factory()->create(['is_active' => false, 'import_status' => ImportStatus::Importing]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->call('updateTableColumnState', 'is_active', (string) $catalog->getKey(), true);

        $this->assertFalse($catalog->refresh()->is_active);
    }

    public function test_toggle_activates_a_catalog_with_a_scraper_before_the_first_import(): void
    {
        $catalog = Catalog::factory()->create([
            'is_active' => false,
            'import_status' => ImportStatus::NotImported,
            'scraper_slug' => 'willtec',
        ]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->call('updateTableColumnState', 'is_active', (string) $catalog->getKey(), true);

        $this->assertTrue($catalog->refresh()->is_active);
    }
}
