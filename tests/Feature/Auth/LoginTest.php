<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_the_panel_matching_the_user_role(): void
    {
        $user = User::factory()->create([
            'role' => Role::Client,
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/cliente');
    }

    public function test_login_ignores_a_stale_intended_url_from_a_different_panel(): void
    {
        // Simulates what happens after logging out of a panel: Filament's own
        // logout redirects back to that panel's root, which — now that the
        // session is guest again — bounces through the login redirect and
        // stores that panel's URL as `url.intended`.
        $this->get('/admin');

        $user = User::factory()->create([
            'role' => Role::Client,
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/cliente');
    }
}
