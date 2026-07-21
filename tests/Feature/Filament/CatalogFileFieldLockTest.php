<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\CreateCatalog;
use App\Filament\Resources\Catalogs\Pages\EditCatalog;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogFileFieldLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_field_is_enabled_on_create(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->assertFormFieldEnabled('file');
    }

    public function test_file_field_is_enabled_when_the_import_has_not_run_yet(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        Storage::disk('local')->put($catalog->file, '{"codigo":"A1"}');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->assertFormFieldEnabled('file');
    }

    public function test_file_field_is_disabled_while_importing(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Importing]);
        Storage::disk('local')->put($catalog->file, '{"codigo":"A1"}');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->assertFormFieldDisabled('file');
    }

    public function test_file_field_is_disabled_once_imported(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        Storage::disk('local')->put($catalog->file, '{"codigo":"A1"}');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->assertFormFieldDisabled('file');
    }

    public function test_file_field_becomes_enabled_again_after_deleting_parts_resets_status(): void
    {
        Storage::fake('local');
        // Mirrors what the "Excluir peças" table action does: reset to NotImported.
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        Storage::disk('local')->put($catalog->file, '{"codigo":"A1"}');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->assertFormFieldEnabled('file');
    }
}
