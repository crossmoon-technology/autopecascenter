<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Oliveira',
            'email' => 'maria@example.com',
            'document' => '12345678901',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ], $overrides);
    }

    public function test_registers_successfully_and_stores_the_registration_ip(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post('/registrar', $this->validPayload());

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'registration_ip' => '203.0.113.10',
        ]);
    }

    public function test_rejects_registration_from_an_ip_already_used_by_another_account(): void
    {
        User::factory()->create(['registration_ip' => '203.0.113.20']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->post('/registrar', $this->validPayload());

        $response->assertSessionHasErrors('registration_ip');
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_allows_registrations_from_different_ips(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
            ->post('/registrar', $this->validPayload(['email' => 'first@example.com', 'document' => '11111111111']));

        // Simula uma segunda pessoa, sessão independente — sem isso o cliente de teste
        // continuaria autenticado como o primeiro usuário registrado.
        $this->post('/logout');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])
            ->post('/registrar', $this->validPayload(['email' => 'second@example.com', 'document' => '22222222222']));

        $response->assertRedirect(route('home'));
        $this->assertDatabaseCount('users', 2);
    }
}
