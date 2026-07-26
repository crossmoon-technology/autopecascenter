<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_login_and_register_buttons_to_a_guest(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('href="'.route('register').'"', false);
    }

    public function test_hides_login_and_register_buttons_when_authenticated(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('href="'.route('login').'"', false);
        $response->assertDontSee('href="'.route('register').'"', false);
        $response->assertSee('Ir para o painel');
    }

    public function test_shows_a_logout_button_when_authenticated(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Client]));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('action="'.route('logout').'"', false);
        $response->assertSee('Sair');
    }

    public function test_the_panel_link_points_to_the_users_own_panel(): void
    {
        $this->actingAs(User::factory()->approvedSeller()->create());

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="/vendedor"', false);
    }

    public function test_shows_logos_of_active_manufacturers_with_a_logo(): void
    {
        Manufacturer::factory()->create(['is_active' => true, 'logo' => 'manufacturers/logos/active.png']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('manufacturers/logos/active.png', false);
    }

    public function test_does_not_show_inactive_manufacturers(): void
    {
        Manufacturer::factory()->create(['is_active' => false, 'logo' => 'manufacturers/logos/inactive.png']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('manufacturers/logos/inactive.png', false);
    }

    public function test_does_not_show_active_manufacturers_without_a_logo(): void
    {
        Manufacturer::factory()->create(['is_active' => true, 'logo' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('partners__logo', false);
    }
}
