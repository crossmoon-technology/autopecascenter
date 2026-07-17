<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\CreateCatalog;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_a_json_catalog_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo 2026',
                'file' => UploadedFile::fake()->createWithContent('catalog.json', '{"parts": []}'),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_rejects_a_non_json_catalog_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo inválido',
                'file' => UploadedFile::fake()->create('catalog.csv', 10, 'text/csv'),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }
}
