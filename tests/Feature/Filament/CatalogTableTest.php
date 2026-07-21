<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Models\Catalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_action_is_available_for_a_catalog_and_does_not_change_it_yet(): void
    {
        $catalog = Catalog::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionExists('import', record: $catalog)
            ->callTableAction('import', record: $catalog);

        $this->assertFalse($catalog->refresh()->is_active);
    }
}
