<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\OrderLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLinkTest extends TestCase
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

    public function test_shows_the_registration_form_for_an_unused_link(): void
    {
        $orderLink = OrderLink::factory()->for(User::factory())->create();

        $response = $this->get($orderLink->publicUrl());

        $response->assertOk();
        $response->assertSee('Complete seu cadastro');
        $response->assertSee('Continuar');
    }

    public function test_returns_404_for_a_nonexistent_token(): void
    {
        $this->get('/pedido/token-que-nao-existe')->assertNotFound();
    }

    public function test_shows_an_already_used_message_for_a_used_link_visited_directly(): void
    {
        $orderLink = OrderLink::factory()->for(User::factory())->used()->create();

        $response = $this->get($orderLink->publicUrl());

        $response->assertOk();
        $response->assertSee('Link já utilizado');
        $response->assertDontSee('Continuar');
    }

    public function test_registering_marks_the_link_as_used_and_creates_a_restricted_client_account(): void
    {
        $seller = User::factory()->create(['role' => Role::Admin]);
        $orderLink = OrderLink::factory()->for($seller)->create();

        $response = $this->post(
            route('order-links.store', ['orderLink' => $orderLink->token]),
            $this->validPayload()
        );

        $response->assertRedirect();

        $orderLink->refresh();
        $this->assertTrue($orderLink->isUsed());

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'role' => Role::Client->value,
            'invited_by_id' => $seller->id,
        ]);

        $registeredUser = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame($registeredUser->id, $orderLink->registered_user_id);
        $this->assertAuthenticatedAs($registeredUser);
    }

    public function test_rejects_registering_to_an_already_used_link(): void
    {
        $orderLink = OrderLink::factory()->for(User::factory())->used()->create();

        $response = $this->post(
            route('order-links.store', ['orderLink' => $orderLink->token]),
            $this->validPayload()
        );

        $response->assertRedirect($orderLink->publicUrl());
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);
        $orderLink = OrderLink::factory()->for(User::factory())->create();

        $response = $this->post(
            route('order-links.store', ['orderLink' => $orderLink->token]),
            $this->validPayload()
        );

        $response->assertSessionHasErrors('email');
        $this->assertFalse($orderLink->fresh()->isUsed());
    }

    public function test_rejects_a_duplicate_document(): void
    {
        User::factory()->create(['document' => '12345678901']);
        $orderLink = OrderLink::factory()->for(User::factory())->create();

        $response = $this->post(
            route('order-links.store', ['orderLink' => $orderLink->token]),
            $this->validPayload()
        );

        $response->assertSessionHasErrors('document');
        $this->assertFalse($orderLink->fresh()->isUsed());
    }

    public function test_does_not_lock_registration_by_ip(): void
    {
        User::factory()->create(['registration_ip' => '203.0.113.20']);
        $orderLink = OrderLink::factory()->for(User::factory())->create();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->post(route('order-links.store', ['orderLink' => $orderLink->token]), $this->validPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'maria@example.com']);
    }
}
