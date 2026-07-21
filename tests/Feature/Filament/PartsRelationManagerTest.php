<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\EditCatalog;
use App\Filament\Resources\Catalogs\Pages\ViewCatalog;
use App\Filament\Resources\Catalogs\RelationManagers\PartsRelationManager;
use App\Models\Catalog;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_parts_scoped_to_the_catalog(): void
    {
        $catalog = Catalog::factory()->create();
        $otherCatalog = Catalog::factory()->create();
        $part = Part::factory()->create(['catalog_id' => $catalog->id, 'codigo' => '16002']);
        $otherPart = Part::factory()->create(['catalog_id' => $otherCatalog->id, 'codigo' => '99999']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PartsRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => ViewCatalog::class,
        ])
            ->assertCanSeeTableRecords([$part])
            ->assertCanNotSeeTableRecords([$otherPart]);
    }

    public function test_deletes_a_part_from_the_edit_page(): void
    {
        $catalog = Catalog::factory()->create();
        $part = Part::factory()->create(['catalog_id' => $catalog->id]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PartsRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => EditCatalog::class,
        ])->callTableAction('delete', $part);

        $this->assertDatabaseMissing('parts', ['id' => $part->id]);
    }

    public function test_edit_and_delete_are_disabled_on_the_read_only_view_page(): void
    {
        $catalog = Catalog::factory()->create();
        $part = Part::factory()->create(['catalog_id' => $catalog->id]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(PartsRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => ViewCatalog::class,
        ])
            ->assertTableActionHidden('edit', record: $part)
            ->assertTableActionHidden('delete', record: $part);
    }
}
