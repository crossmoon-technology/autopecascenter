<?php

namespace Tests\Feature\Auth;

use App\Mail\Auth\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dá uma saída pra quem nunca recebeu o e-mail original (ex: instabilidade
     * do provedor) — antes disso a conta ficava travada pra sempre.
     */
    public function test_resends_the_verification_email_for_an_unverified_account(): void
    {
        Mail::fake();
        $user = User::factory()->seller()->unverified()->create();

        $response = $this->post(route('verification.resend'), ['email' => $user->email]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Mail::assertQueued(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->user->is($user));
    }

    /**
     * Não pode revelar se o e-mail existe ou já está verificado — mesmo
     * princípio de Password::sendResetLink(): resposta sempre igual.
     */
    public function test_is_silent_for_an_email_that_does_not_exist(): void
    {
        Mail::fake();

        $response = $this->post(route('verification.resend'), ['email' => 'ninguem@example.com']);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Mail::assertNothingQueued();
    }

    public function test_is_silent_for_an_already_verified_account(): void
    {
        Mail::fake();
        $user = User::factory()->seller()->create();

        $this->post(route('verification.resend'), ['email' => $user->email]);

        Mail::assertNothingQueued();
    }

    /**
     * Mesmo cooldown de 60s do reset de senha (config('auth.passwords.users.throttle'))
     * — sem isso, viraria um jeito de bombardear a caixa de entrada de alguém.
     */
    public function test_does_not_resend_within_the_throttle_window(): void
    {
        Mail::fake();
        $user = User::factory()->seller()->unverified()->create();

        $this->post(route('verification.resend'), ['email' => $user->email]);
        $this->post(route('verification.resend'), ['email' => $user->email]);

        Mail::assertQueued(VerifyEmailMail::class, 1);
    }

    public function test_resends_again_after_the_throttle_window_expires(): void
    {
        Mail::fake();
        $user = User::factory()->seller()->unverified()->create();

        $this->post(route('verification.resend'), ['email' => $user->email]);
        $this->travel(61)->seconds();
        $this->post(route('verification.resend'), ['email' => $user->email]);

        Mail::assertQueued(VerifyEmailMail::class, 2);
    }

    public function test_rejects_an_invalid_email(): void
    {
        $response = $this->post(route('verification.resend'), ['email' => 'not-an-email']);

        $response->assertSessionHasErrors('email');
    }
}
