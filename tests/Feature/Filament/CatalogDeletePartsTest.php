<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\ListCatalogs;
use App\Models\Catalog;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogDeletePartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_the_catalogs_parts_and_deactivates_it(): void
    {
        $catalog = Catalog::factory()->create(['is_active' => true]);
        Part::factory()->count(3)->create(['catalog_id' => $catalog->getKey()]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->callTableAction('deleteParts', record: $catalog);

        $this->assertSame(0, Part::query()->where('catalog_id', $catalog->id)->count());
        $this->assertFalse($catalog->refresh()->is_active);
    }

    public function test_action_is_hidden_when_the_catalog_has_no_parts(): void
    {
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListCatalogs::class)
            ->assertTableActionHidden('deleteParts', record: $catalog);
    }
}
