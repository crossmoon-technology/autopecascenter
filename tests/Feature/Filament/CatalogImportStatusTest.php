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

class CatalogImportStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_not_imported_status(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertSeeHtml('Não importado');
    }

    public function test_shows_importing_status(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Importing]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertSeeHtml('animation: spin 2.5s linear infinite;')
            ->assertSeeHtml('Importando');
    }

    public function test_shows_imported_status(): void
    {
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertSeeHtml('Já importado');
    }
}
