<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Informativos\Pages\EditInformativo;
use App\Filament\Resources\Informativos\Pages\ListInformativos;
use App\Models\Catalog;
use App\Models\Informativo;
use App\Models\Informativo\Enums\InformativoType;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InformativoResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_informativos_from_every_catalog(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap']);
        $cofapCatalog = Catalog::factory()->create(['manufacturer_id' => $cofap->id, 'name' => 'Catálogo Cofap']);
        $informativo = Informativo::factory()->create(['catalog_id' => $cofapCatalog->id, 'original_name' => 'folder-cofap.pdf']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListInformativos::class)
            ->assertCanSeeTableRecords([$informativo])
            ->assertSee('Catálogo Cofap')
            ->assertSee('folder-cofap.pdf');
    }

    public function test_filters_by_catalog(): void
    {
        $catalogA = Catalog::factory()->create();
        $catalogB = Catalog::factory()->create();
        $informativoA = Informativo::factory()->create(['catalog_id' => $catalogA->id]);
        $informativoB = Informativo::factory()->create(['catalog_id' => $catalogB->id]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListInformativos::class)
            ->filterTable('catalog_id', $catalogA->id)
            ->assertCanSeeTableRecords([$informativoA])
            ->assertCanNotSeeTableRecords([$informativoB]);
    }

    public function test_filters_by_manufacturer(): void
    {
        $cofap = Manufacturer::factory()->create(['name' => 'Cofap']);
        $sabo = Manufacturer::factory()->create(['name' => 'Sabó']);
        $cofapCatalog = Catalog::factory()->create(['manufacturer_id' => $cofap->id]);
        $saboCatalog = Catalog::factory()->create(['manufacturer_id' => $sabo->id]);
        $cofapInformativo = Informativo::factory()->create(['catalog_id' => $cofapCatalog->id]);
        $saboInformativo = Informativo::factory()->create(['catalog_id' => $saboCatalog->id]);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListInformativos::class)
            ->filterTable('manufacturer_id', $cofap->id)
            ->assertCanSeeTableRecords([$cofapInformativo])
            ->assertCanNotSeeTableRecords([$saboInformativo]);
    }

    public function test_bulk_upload_action_lets_you_pick_the_catalog_and_creates_one_informativo_per_file(): void
    {
        Storage::fake('public');
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListInformativos::class)
            ->callTableAction('addInformativos', data: [
                'catalog_id' => $catalog->id,
                'files' => [
                    UploadedFile::fake()->create('folder.pdf', 300, 'application/pdf'),
                    UploadedFile::fake()->image('banner.jpg'),
                ],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(2, Informativo::query()->where('catalog_id', $catalog->id)->count());
        $this->assertSame(
            InformativoType::Pdf,
            Informativo::query()->where('original_name', 'folder.pdf')->firstOrFail()->type
        );
    }

    public function test_can_edit_the_catalog_and_name_of_an_informativo(): void
    {
        $originalCatalog = Catalog::factory()->create();
        $newCatalog = Catalog::factory()->create();
        $informativo = Informativo::factory()->create(['catalog_id' => $originalCatalog->id, 'original_name' => 'antigo.pdf']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(EditInformativo::class, ['record' => $informativo->getKey()])
            ->fillForm([
                'catalog_id' => $newCatalog->id,
                'original_name' => 'novo.pdf',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $informativo->refresh();
        $this->assertSame($newCatalog->id, $informativo->catalog_id);
        $this->assertSame('novo.pdf', $informativo->original_name);
    }

    public function test_deletes_an_informativo_from_the_list(): void
    {
        $informativo = Informativo::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListInformativos::class)->callTableAction('delete', $informativo);

        $this->assertDatabaseMissing('informativos', ['id' => $informativo->id]);
    }
}
