<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Parts\Pages\ListParts;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PartsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_manufacturer_icon_via_the_catalog(): void
    {
        Storage::fake('public');
        $manufacturer = Manufacturer::factory()->create(['icon' => 'manufacturers/icons/cofap.png']);
        Storage::disk('public')->put($manufacturer->icon, 'fake-icon-content');
        $catalog = Catalog::factory()->create(['manufacturer_id' => $manufacturer->getKey()]);
        Part::factory()->create(['catalog_id' => $catalog->getKey()]);

        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListParts::class)
            ->assertSeeHtml(Storage::disk('public')->url('manufacturers/icons/cofap.png'));
    }
}
