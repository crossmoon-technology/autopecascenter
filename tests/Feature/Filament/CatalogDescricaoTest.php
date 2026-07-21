<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\CreateCatalog;
use App\Filament\Resources\Catalogs\Pages\EditCatalog;
use App\Models\Catalog;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogDescricaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_a_descricao_on_create(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->id,
                'name' => 'Catálogo Leve 2026',
                'descricao' => 'Catálogo de peças leves atualizado em 2026.',
                'file' => UploadedFile::fake()->createWithContent('catalogo.jsonl', json_encode(['codigo' => '1'])),
                'extracted_at' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'Catálogo de peças leves atualizado em 2026.',
            Catalog::firstOrFail()->descricao
        );
    }

    public function test_descricao_is_nullable(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(CreateCatalog::class)
            ->fillForm([
                'manufacturer_id' => $manufacturer->id,
                'name' => 'Catálogo sem descrição',
                'file' => UploadedFile::fake()->createWithContent('catalogo.jsonl', json_encode(['codigo' => '1'])),
                'extracted_at' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Catalog::firstOrFail()->descricao);
    }

    public function test_can_edit_the_descricao(): void
    {
        Storage::fake('local');
        $catalog = Catalog::factory()->create(['descricao' => null]);
        Storage::disk('local')->put($catalog->file, json_encode(['codigo' => '1']));
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditCatalog::class, ['record' => $catalog->getKey()])
            ->fillForm(['descricao' => 'Atualizado agora.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Atualizado agora.', $catalog->refresh()->descricao);
    }
}
