<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanosPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_default_plan_values_when_nothing_is_saved(): void
    {
        $response = $this->get(route('planos'));

        $response->assertOk();
        $response->assertSee('Avaliação gratuita');
        $response->assertSee('Básico');
        $response->assertSee('Profissional');
        $response->assertSee('R$ 99', false);
        $response->assertSee('R$ 249', false);
        $response->assertSee('Até 50 cotações por mês');
        $response->assertDontSee('Empresarial');
    }

    public function test_comecar_avaliacao_gratuita_links_straight_to_seller_registration(): void
    {
        $response = $this->get(route('planos'));

        $response->assertOk();
        $response->assertSee('href="'.route('register.seller').'" class="btn btn--outline btn--block">Começar avaliação gratuita', false);
    }

    public function test_page_renders_saved_plan_values_instead_of_defaults(): void
    {
        Setting::set('plan_basico_price', 'R$ 119');
        Setting::set('plan_basico_features', "Recurso customizado A\nRecurso customizado B");

        $response = $this->get(route('planos'));

        $response->assertOk();
        $response->assertSee('R$ 119', false);
        $response->assertSee('Recurso customizado A');
        $response->assertSee('Recurso customizado B');
        $response->assertDontSee('Até 50 cotações por mês');
    }
}
