<?php

namespace Tests\Feature\Filament;

use App\Enums\Role;
use App\Filament\Pages\Buscas\Iframes;
use App\Filament\Pages\Buscas\Orders;
use App\Filament\Pages\Configuracoes\ManufacturerPreferences;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Informativos\Pages\ListInformativos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cobre os 4 formatos estruturais diferentes usados pelas páginas do vendedor (ver
 * App\Filament\Concerns\HasHelpAction): subclasse do Dashboard padrão do Filament, página
 * simples, página com tabela (que já tem seus próprios headerActions na tabela, separados
 * dos da página) e página de listagem de Resource (que precisa mesclar com os
 * headerActions padrão do Filament, ex: o CreateAction).
 */
class PageHelpActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_the_help_modal(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $component = Livewire::test(Dashboard::class)->mountAction('help')->assertActionMounted('help');

        $this->assertSame('Como funciona o Painel de Controle', $component->instance()->getMountedAction()->getModalHeading());
    }

    public function test_iframes_shows_the_help_modal(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $component = Livewire::test(Iframes::class)->mountAction('help')->assertActionMounted('help');

        $this->assertSame('Como funciona a busca por Iframes', $component->instance()->getMountedAction()->getModalHeading());
    }

    public function test_orders_shows_the_help_modal(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $component = Livewire::test(Orders::class)->mountAction('help')->assertActionMounted('help');

        $this->assertSame('Como funciona a página de Pedidos', $component->instance()->getMountedAction()->getModalHeading());
    }

    public function test_manufacturer_preferences_shows_the_help_modal(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $component = Livewire::test(ManufacturerPreferences::class)->mountAction('help')->assertActionMounted('help');

        $this->assertSame('Como funciona "Fabricantes habilitados"', $component->instance()->getMountedAction()->getModalHeading());
    }

    public function test_informativos_list_shows_the_help_modal_alongside_the_default_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));

        $component = Livewire::test(ListInformativos::class)->mountAction('help')->assertActionMounted('help');

        $this->assertSame('Como funciona a página de Informativos', $component->instance()->getMountedAction()->getModalHeading());
    }
}
