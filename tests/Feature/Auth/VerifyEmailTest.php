<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    private function signedUrlFor(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id]);
    }

    public function test_a_valid_signed_link_marks_the_email_as_verified(): void
    {
        $user = User::factory()->seller()->unverified()->create();

        $response = $this->get($this->signedUrlFor($user));

        $response->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_visiting_again_after_already_verified_is_harmless(): void
    {
        $user = User::factory()->seller()->create();
        $verified_at = $user->email_verified_at;

        $this->get($this->signedUrlFor($user));

        $this->assertTrue($user->fresh()->email_verified_at->equalTo($verified_at));
    }

    public function test_a_tampered_link_is_rejected(): void
    {
        $user = User::factory()->seller()->unverified()->create();
        $url = $this->signedUrlFor($user).'&tampered=1';

        $response = $this->get($url);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $user = User::factory()->seller()->unverified()->create();
        $url = $this->signedUrlFor($user);

        $this->travel(61)->minutes();

        $response = $this->get($url);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * O upgrade de Cliente pra Vendedor só é efetivado aqui, na confirmação do link —
     * nunca no submit do formulário de cadastro (ver AuthService::registerSeller() e
     * RegisterTest::test_does_not_change_the_role_of_an_existing_client_account_until_verified()).
     */
    public function test_applies_a_pending_seller_upgrade_and_verifies_the_email(): void
    {
        $otherSeller = User::factory()->create(['role' => Role::Seller]);
        $client = User::factory()->clientOf($otherSeller)->create();
        $client->forceFill([
            'pending_seller_upgrade' => [
                'name' => 'Maria Vendedora',
                'document' => '99988877766',
                'password' => Hash::make('Str0ng!Passw0rd'),
                'registration_ip' => '203.0.113.70',
            ],
        ])->save();

        $response = $this->get($this->signedUrlFor($client));

        $response->assertRedirect(route('login'));

        $upgraded = $client->fresh();
        $this->assertSame(Role::Seller, $upgraded->role);
        $this->assertSame('Maria Vendedora', $upgraded->name);
        $this->assertSame('99988877766', $upgraded->document);
        $this->assertSame('203.0.113.70', $upgraded->registration_ip);
        $this->assertNotNull($upgraded->referral_code);
        $this->assertNull($upgraded->pending_seller_upgrade);
        $this->assertTrue($upgraded->hasVerifiedEmail());
        $this->assertTrue($upgraded->isLinkedToSeller($otherSeller));
    }

    public function test_upgraded_account_can_log_in_with_the_new_password(): void
    {
        $client = User::factory()->create(['role' => Role::Client]);
        $client->forceFill([
            'pending_seller_upgrade' => [
                'name' => $client->name,
                'document' => $client->document,
                'password' => Hash::make('Str0ng!Passw0rd'),
                'registration_ip' => '203.0.113.71',
            ],
        ])->save();

        $this->get($this->signedUrlFor($client));

        $response = $this->post('/login', [
            'email' => $client->email,
            'password' => 'Str0ng!Passw0rd',
        ]);

        $response->assertRedirect(route('choose-plan'));
        $this->assertAuthenticatedAs($client->fresh());
    }

    public function test_a_seller_with_no_pending_upgrade_is_unaffected(): void
    {
        $user = User::factory()->seller()->unverified()->create();

        $this->get($this->signedUrlFor($user));

        $this->assertNull($user->fresh()->pending_seller_upgrade);
        $this->assertSame(Role::Seller, $user->fresh()->role);
    }
}
