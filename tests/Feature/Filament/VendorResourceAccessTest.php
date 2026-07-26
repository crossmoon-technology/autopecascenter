<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Resources\Catalogs\CatalogResource;
use App\Filament\Resources\Informativos\InformativoResource;
use App\Filament\Resources\Informativos\Pages\ListInformativos;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use App\Filament\Resources\Parts\PartResource;
use App\Models\Catalog;
use App\Models\Informativo;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Contas de vendedor (Role::Seller) não têm acesso aos cadastros de Fabricantes,
 * Catálogos e Peças (isso é coisa de Role::SuperAdmin) — ver App\Policies\*Policy. O
 * cadastro de Informativos é a exceção: o vendedor pode ver a lista, só não pode
 * criar/editar/apagar.
 */
class VendorResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manufacturer_resource_is_hidden_from_a_vendor(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->assertFalse(ManufacturerResource::canViewAny());
        $this->get(ManufacturerResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_manufacturer_resource_is_visible_to_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->assertTrue(ManufacturerResource::canViewAny());
    }

    public function test_catalog_resource_is_hidden_from_a_vendor(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->assertFalse(CatalogResource::canViewAny());
        $this->get(CatalogResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_catalog_resource_is_visible_to_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->assertTrue(CatalogResource::canViewAny());
    }

    public function test_part_resource_is_hidden_from_a_vendor(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->assertFalse(PartResource::canViewAny());
        $this->get(PartResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_part_resource_is_visible_to_a_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->assertTrue(PartResource::canViewAny());
    }

    public function test_a_vendor_can_view_informativos_but_not_write_to_them(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->assertTrue(InformativoResource::canViewAny());
        $this->get(InformativoResource::getUrl('index', panel: 'admin'))->assertSuccessful();

        $this->assertFalse(InformativoResource::canCreate());
    }

    public function test_a_vendor_cannot_edit_or_delete_an_informativo(): void
    {
        $informativo = Informativo::factory()->create();
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->assertFalse(InformativoResource::canEdit($informativo));
        $this->assertFalse(InformativoResource::canDelete($informativo));
    }

    public function test_a_super_admin_can_still_fully_manage_informativos(): void
    {
        $informativo = Informativo::factory()->create();
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        $this->assertTrue(InformativoResource::canViewAny());
        $this->assertTrue(InformativoResource::canCreate());
        $this->assertTrue(InformativoResource::canEdit($informativo));
        $this->assertTrue(InformativoResource::canDelete($informativo));
    }

    public function test_a_vendor_cannot_directly_open_a_manufacturers_create_page(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->get(ManufacturerResource::getUrl('create', panel: 'admin'))->assertForbidden();
    }

    public function test_a_vendor_cannot_directly_open_a_catalogs_edit_page(): void
    {
        $catalog = Catalog::factory()->create();
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->get(CatalogResource::getUrl('edit', ['record' => $catalog], panel: 'admin'))->assertForbidden();
    }

    public function test_a_vendor_cannot_directly_open_a_parts_edit_page(): void
    {
        $part = Part::factory()->create();
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->get(PartResource::getUrl('edit', ['record' => $part], panel: 'admin'))->assertForbidden();
    }

    public function test_a_vendor_cannot_directly_open_an_informativo_edit_page(): void
    {
        $informativo = Informativo::factory()->create();
        $this->actingAs(User::factory()->approvedSeller()->create());

        $this->get(InformativoResource::getUrl('edit', ['record' => $informativo], panel: 'admin'))->assertForbidden();
    }

    /**
     * addInformativos é uma Action customizada (ver InformativosTable), não o CreateAction
     * padrão do Filament — ela não checa a policy sozinha, precisa do ->visible() explícito.
     */
    public function test_a_vendor_does_not_see_the_add_informativos_button(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        Livewire::test(ListInformativos::class)->assertTableActionHidden('addInformativos');
    }

    public function test_a_super_admin_sees_the_add_informativos_button(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(ListInformativos::class)->assertTableActionVisible('addInformativos');
    }
}
