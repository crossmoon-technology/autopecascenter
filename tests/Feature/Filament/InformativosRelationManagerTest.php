<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\Pages\EditCatalog;
use App\Filament\Resources\Catalogs\RelationManagers\InformativosRelationManager;
use App\Models\Catalog;
use App\Models\Informativo;
use App\Models\Informativo\Enums\InformativoType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InformativosRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_uploads_multiple_files_as_separate_informativos(): void
    {
        Storage::fake('public');
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(InformativosRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => EditCatalog::class,
        ])
            ->callTableAction('addInformativos', data: [
                'files' => [
                    UploadedFile::fake()->create('catalogo-2026.pdf', 500, 'application/pdf'),
                    UploadedFile::fake()->image('banner.png'),
                ],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(2, Informativo::query()->where('catalog_id', $catalog->id)->count());

        $pdf = Informativo::query()->where('original_name', 'catalogo-2026.pdf')->firstOrFail();
        $this->assertSame(InformativoType::Pdf, $pdf->type);

        $image = Informativo::query()->where('original_name', 'banner.png')->firstOrFail();
        $this->assertSame(InformativoType::Imagem, $image->type);

        Storage::disk('public')->assertExists($pdf->file);
        Storage::disk('public')->assertExists($image->file);
    }

    public function test_bulk_upload_scopes_informativos_to_the_owning_catalog(): void
    {
        Storage::fake('public');
        $catalog = Catalog::factory()->create();
        $otherCatalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(InformativosRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => EditCatalog::class,
        ])->callTableAction('addInformativos', data: [
            'files' => [UploadedFile::fake()->create('folder.pdf', 200, 'application/pdf')],
        ]);

        $this->assertSame(1, Informativo::query()->where('catalog_id', $catalog->id)->count());
        $this->assertSame(0, Informativo::query()->where('catalog_id', $otherCatalog->id)->count());
    }

    public function test_rejects_an_unsupported_file_type(): void
    {
        Storage::fake('public');
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(InformativosRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => EditCatalog::class,
        ])
            ->callTableAction('addInformativos', data: [
                'files' => [UploadedFile::fake()->create('planilha.xlsx', 100, 'application/vnd.ms-excel')],
            ])
            ->assertHasActionErrors(['files']);

        $this->assertDatabaseCount('informativos', 0);
    }

    public function test_lists_existing_informativos(): void
    {
        $catalog = Catalog::factory()->create();
        $informativo = Informativo::factory()->create(['catalog_id' => $catalog->id, 'original_name' => 'catalogo.pdf']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(InformativosRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => EditCatalog::class,
        ])->assertCanSeeTableRecords([$informativo]);
    }

    public function test_deletes_an_informativo(): void
    {
        Storage::fake('public');
        $catalog = Catalog::factory()->create();
        $informativo = Informativo::factory()->create(['catalog_id' => $catalog->id]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(InformativosRelationManager::class, [
            'ownerRecord' => $catalog,
            'pageClass' => EditCatalog::class,
        ])->callTableAction('delete', $informativo);

        $this->assertDatabaseMissing('informativos', ['id' => $informativo->id]);
    }
}
