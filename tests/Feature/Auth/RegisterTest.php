<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Mail\Auth\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
            ->post('/registrar/vendedor', $this->validPayload());

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'registration_ip' => '203.0.113.10',
        ]);
    }

    public function test_creates_the_account_without_a_plan_or_verified_email_and_does_not_log_in(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
            ->post('/registrar/vendedor', $this->validPayload());

        $this->assertGuest();
        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame(Role::Seller, $user->role);
        $this->assertNull($user->plan);
        $this->assertNull($user->trial_ends_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_sends_a_verification_email(): void
    {
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.45'])
            ->post('/registrar/vendedor', $this->validPayload());

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();

        Mail::assertQueued(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->user->is($user));
    }

    public function test_rejects_registration_from_an_ip_already_used_by_another_account(): void
    {
        User::factory()->create(['registration_ip' => '203.0.113.20']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->post('/registrar/vendedor', $this->validPayload());

        $response->assertSessionHasErrors('registration_ip');
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_allows_registrations_from_different_ips(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
            ->post('/registrar/vendedor', $this->validPayload(['email' => 'first@example.com', 'document' => '11111111111']));

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])
            ->post('/registrar/vendedor', $this->validPayload(['email' => 'second@example.com', 'document' => '22222222222']));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 2);
    }

    /**
     * Alguém que já é Cliente (convidado por outro vendedor) pode se cadastrar como
     * Seller com o mesmo CPF, contanto que use um e-mail diferente — o documento agora é
     * único por papel, não globalmente. Isso cria uma SEGUNDA conta (email diferente);
     * ver a seção "upgrade" abaixo pro caso de usar o MESMO e-mail da conta Cliente.
     */
    public function test_allows_registering_as_seller_with_the_same_document_as_an_existing_client(): void
    {
        User::factory()->create(['role' => Role::Client, 'document' => '12345678901']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->post('/registrar/vendedor', $this->validPayload());

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['email' => 'maria@example.com', 'document' => '12345678901', 'role' => Role::Seller->value]);
    }

    public function test_rejects_registering_as_seller_with_the_same_document_as_an_existing_seller(): void
    {
        User::factory()->create(['role' => Role::Seller, 'document' => '12345678901']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.51'])
            ->post('/registrar/vendedor', $this->validPayload());

        $response->assertSessionHasErrors('document');
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    /**
     * Registrar como vendedor usando o MESMO e-mail de uma conta Cliente existente
     * PREPARA um upgrade dessa conta (mesmo id) em vez de criar uma segunda — mas só
     * fica pendente até a confirmação por e-mail (ver
     * AuthService::applyPendingSellerUpgradeIfAny(), coberto em VerifyEmailTest). Sem
     * isso, alguém poderia trocar a senha e o role de uma conta Cliente alheia só
     * sabendo o e-mail dela, sem provar que é o dono.
     */
    public function test_does_not_change_the_role_of_an_existing_client_account_until_verified(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($otherSeller)->create(['email' => 'maria@example.com']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
            ->post('/registrar/vendedor', $this->validPayload());

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 2);

        $stillClient = $client->fresh();
        $this->assertSame($client->id, $stillClient->id);
        $this->assertSame(Role::Client, $stillClient->role);
        $this->assertNotNull($stillClient->pending_seller_upgrade);
    }

    public function test_the_account_keeps_working_as_a_client_while_the_upgrade_is_pending(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($otherSeller)->create(['email' => 'maria@example.com']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.61'])
            ->post('/registrar/vendedor', $this->validPayload());

        $this->assertTrue($client->fresh()->isLinkedToSeller($otherSeller));
        $this->assertNull($client->fresh()->referral_code);
    }

    public function test_rejects_registration_when_the_email_already_belongs_to_a_seller(): void
    {
        User::factory()->create(['role' => Role::Seller, 'email' => 'maria@example.com']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.64'])
            ->post('/registrar/vendedor', $this->validPayload());

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }
}
