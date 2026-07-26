<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\EditCatalog;
use App\Filament\Resources\Catalogs\Pages\ViewCatalog;
use App\Models\Catalog;
use App\Models\Catalog\Enums\ImportStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ViewCatalogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_original_file_field(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create();
        Storage::disk('local')->put($catalog->file, '{}');

        Livewire::test(ViewCatalog::class, ['record' => $catalog->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Arquivo original');
    }

    public function test_does_not_show_the_update_file_entry_when_there_is_no_update_yet(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create(['update_file' => null]);
        Storage::disk('local')->put($catalog->file, '{}');

        Livewire::test(ViewCatalog::class, ['record' => $catalog->getRouteKey()])
            ->assertSuccessful()
            ->assertDontSee('Última atualização');
    }

    public function test_shows_the_update_file_entry_when_present(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create(['update_file' => 'catalogs/updates/abc123.jsonl']);
        Storage::disk('local')->put($catalog->file, '{}');
        Storage::disk('local')->put($catalog->update_file, '{}');

        Livewire::test(ViewCatalog::class, ['record' => $catalog->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Última atualização');
    }

    public function test_shows_the_translated_import_status_label_not_the_raw_enum_value(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        Storage::disk('local')->put($catalog->file, '{}');

        Livewire::test(ViewCatalog::class, ['record' => $catalog->getRouteKey()])
            ->assertSuccessful()
            // TextInput mostra o valor dentro do wire:snapshot (JSON serializado, com
            // acentos escapados em \uXXXX) — não como texto UTF-8 literal na árvore.
            ->assertSeeHtml('J\\u00e1 importado');
    }

    public function test_does_not_show_the_update_file_field_when_creating_or_editing(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $catalog = Catalog::factory()->create(['update_file' => 'catalogs/updates/abc123.jsonl']);
        Storage::disk('local')->put($catalog->file, '{}');
        Storage::disk('local')->put($catalog->update_file, '{}');

        Livewire::test(EditCatalog::class, ['record' => $catalog->getRouteKey()])
            ->assertSuccessful()
            ->assertDontSee('Última atualização');
    }
}
