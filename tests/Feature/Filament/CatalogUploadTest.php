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

    public function test_accepts_a_jsonl_catalog_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo 2026',
                'file' => UploadedFile::fake()->createWithContent('catalog.jsonl', "{\"code\":\"A1\"}\n{\"code\":\"A2\"}\n"),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_accepts_a_catalog_without_a_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo sem arquivo',
                'extracted_at' => '2026-07-01',
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_accepts_a_catalog_without_an_extraction_date(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo sem data de extração',
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_accepts_a_catalog_without_a_file_or_an_extraction_date(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo mínimo',
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_rejects_a_non_jsonl_catalog_file(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo inválido',
                'file' => UploadedFile::fake()->createWithContent('catalog.json', '{"code":"A1"}'),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }

    public function test_rejects_a_file_with_a_utf8_bom(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo com BOM',
                'file' => UploadedFile::fake()->createWithContent(
                    'catalog.jsonl',
                    "\xEF\xBB\xBF{\"codigo\":\"A1\"}\n"
                ),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }

    public function test_rejects_a_file_with_crlf_line_endings(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo com CRLF',
                'file' => UploadedFile::fake()->createWithContent(
                    'catalog.jsonl',
                    "{\"codigo\":\"A1\"}\r\n{\"codigo\":\"A2\"}\r\n"
                ),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }

    public function test_rejects_a_file_with_an_invalid_json_line(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
        $manufacturer = Manufacturer::factory()->create();

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->getKey(),
                'name' => 'Catálogo com linha inválida',
                'file' => UploadedFile::fake()->createWithContent(
                    'catalog.jsonl',
                    "{\"codigo\":\"A1\"}\nnot valid json\n"
                ),
                'extracted_at' => '2026-07-01',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }
}
