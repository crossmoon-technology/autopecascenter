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

class CatalogImportButtonVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_button_is_visible_when_not_imported(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionVisible('import', record: $catalog);
    }

    public function test_import_button_is_hidden_while_importing(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Importing]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionHidden('import', record: $catalog);
    }

    public function test_import_button_is_hidden_once_already_imported(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionHidden('import', record: $catalog);
    }
}
