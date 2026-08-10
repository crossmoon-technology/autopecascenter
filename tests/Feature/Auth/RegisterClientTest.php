<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Mail\Auth\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterClientTest extends TestCase
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

    public function test_registers_successfully_and_links_to_the_seller_by_code(): void
    {
        // O código não é escolhido por ninguém, quem gera é User::booted() ao criar o
        // vendedor — por isso o teste lê o valor gerado em vez de forçar um fixo.
        $seller = User::factory()->create(['role' => Role::Seller]);

        $response = $this->post('/registrar/cliente', $this->validPayload(['referral_code' => $seller->referral_code]));

        $response->assertRedirect(route('login'));

        $client = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame(Role::Client, $client->role);
        $this->assertTrue($client->isLinkedToSeller($seller));
    }

    /**
     * Igual ao cadastro de vendedor, o cliente também precisa confirmar o e-mail antes
     * de poder entrar — não fica mais logado automaticamente.
     */
    public function test_requires_email_verification_before_login(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);

        $this->post('/registrar/cliente', $this->validPayload(['referral_code' => $seller->referral_code]));

        $client = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertFalse($client->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_sends_a_verification_email(): void
    {
        Mail::fake();

        $seller = User::factory()->create(['role' => Role::Seller]);

        $this->post('/registrar/cliente', $this->validPayload(['referral_code' => $seller->referral_code]));

        $client = User::query()->where('email', 'maria@example.com')->firstOrFail();

        Mail::assertQueued(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->user->is($client));
    }

    public function test_rejects_an_invalid_referral_code(): void
    {
        $response = $this->post('/registrar/cliente', $this->validPayload(['referral_code' => 'NAO-EXISTE']));

        $response->assertSessionHasErrors('referral_code');
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
        $this->assertGuest();
    }

    /**
     * O código só é reconhecido se pertencer a um Role::Seller — referral_code fica fora
     * do Fillable de propósito (ver User::booted()), então só forceFill consegue simular
     * uma conta Cliente com um código preenchido, estado que a aplicação em si nunca
     * produz sozinha.
     */
    public function test_rejects_a_referral_code_that_does_not_belong_to_a_seller(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);
        $client->forceFill(['referral_code' => 'CODE1234'])->save();

        $response = $this->post('/registrar/cliente', $this->validPayload(['referral_code' => 'CODE1234']));

        $response->assertSessionHasErrors('referral_code');
        $this->assertGuest();
    }

    public function test_rejects_a_duplicate_email(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        User::factory()->create(['email' => 'maria@example.com']);

        $response = $this->post('/registrar/cliente', $this->validPayload(['referral_code' => $seller->referral_code]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_rejects_a_duplicate_document_among_clients(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        User::factory()->create(['role' => Role::Client, 'document' => '12345678901']);

        $response = $this->post('/registrar/cliente', $this->validPayload(['referral_code' => $seller->referral_code]));

        $response->assertSessionHasErrors('document');
        $this->assertGuest();
    }

    /**
     * O documento é único por papel, não globalmente — quem já é vendedor pode se
     * cadastrar aqui como cliente com o mesmo CPF (ver User::linkToSeller()).
     */
    public function test_allows_registering_with_the_same_document_as_an_existing_seller(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller, 'document' => '12345678901']);

        $response = $this->post('/registrar/cliente', $this->validPayload(['referral_code' => $seller->referral_code]));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['document' => '12345678901', 'role' => Role::Client->value]);
    }

    /**
     * Diferente do cadastro de vendedor (que trava por IP, ver RegisterRequest), o
     * cadastro de cliente não tem essa restrição — vários clientes podem vir da mesma
     * rede (ex: a mesma oficina cadastrando funcionários diferentes). RegisterClientRequest
     * nem tem uma regra pra registration_ip, então basta confirmar que o cadastro
     * funciona normalmente mesmo quando o IP já foi usado por outra conta.
     */
    public function test_does_not_lock_registration_by_ip(): void
    {
        $seller = User::factory()->create(['role' => Role::Seller]);
        User::factory()->create(['registration_ip' => '203.0.113.20']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->post('/registrar/cliente', $this->validPayload(['referral_code' => $seller->referral_code]));

        $response->assertRedirect(route('login'));
        $this->assertTrue(User::query()->where('email', 'maria@example.com')->firstOrFail()->isLinkedToSeller($seller));
    }
}
