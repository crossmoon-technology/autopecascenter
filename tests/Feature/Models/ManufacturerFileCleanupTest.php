<?php

namespace Tests\Feature\Models;

use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManufacturerFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_keeps_the_files_on_disk(): void
    {
        Storage::fake('public');
        $manufacturer = Manufacturer::factory()->create([
            'logo' => 'manufacturers/logos/logo.png',
            'icon' => 'manufacturers/icons/icon.png',
        ]);
        Storage::disk('public')->put($manufacturer->logo, 'conteudo');
        Storage::disk('public')->put($manufacturer->icon, 'conteudo');

        $manufacturer->delete();

        Storage::disk('public')->assertExists($manufacturer->logo);
        Storage::disk('public')->assertExists($manufacturer->icon);
    }

    public function test_force_deleting_removes_the_files_from_disk(): void
    {
        Storage::fake('public');
        $manufacturer = Manufacturer::factory()->create([
            'logo' => 'manufacturers/logos/logo.png',
            'icon' => 'manufacturers/icons/icon.png',
        ]);
        Storage::disk('public')->put($manufacturer->logo, 'conteudo');
        Storage::disk('public')->put($manufacturer->icon, 'conteudo');

        $manufacturer->forceDelete();

        Storage::disk('public')->assertMissing('manufacturers/logos/logo.png');
        Storage::disk('public')->assertMissing('manufacturers/icons/icon.png');
    }

    public function test_force_deleting_a_manufacturer_without_any_file_does_not_error(): void
    {
        Storage::fake('public');
        $manufacturer = Manufacturer::factory()->create(['logo' => null, 'icon' => null]);

        $manufacturer->forceDelete();

        $this->assertModelMissing($manufacturer);
    }
}
