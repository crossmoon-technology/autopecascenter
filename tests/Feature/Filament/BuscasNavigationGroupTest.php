<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Api;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Filament\Pages\Buscas\Iframes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BuscasNavigationGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_iframes_page_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Iframes::class)->assertSuccessful();
    }

    public function test_api_page_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));

        Livewire::test(Api::class)->assertSuccessful();
    }

    public function test_all_three_search_pages_share_the_buscas_navigation_group(): void
    {
        $this->assertSame('Buscas', Iframes::getNavigationGroup());
        $this->assertSame('Buscas', CatalogDatabaseSearch::getNavigationGroup());
        $this->assertSame('Buscas', Api::getNavigationGroup());
    }
}
