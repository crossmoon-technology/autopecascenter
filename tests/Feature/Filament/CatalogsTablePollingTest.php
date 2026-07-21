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

class CatalogsTablePollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_polls_while_a_catalog_is_importing(): void
    {
        Catalog::factory()->create(['import_status' => ImportStatus::Importing]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $test = Livewire::test(ListCatalogs::class);

        $this->assertSame('3s', $test->instance()->getTable()->getPollingInterval());
    }

    public function test_table_does_not_poll_when_nothing_is_importing(): void
    {
        Catalog::factory()->create(['import_status' => ImportStatus::NotImported]);
        Catalog::factory()->create(['import_status' => ImportStatus::Imported]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $test = Livewire::test(ListCatalogs::class);

        $this->assertNull($test->instance()->getTable()->getPollingInterval());
    }
}
