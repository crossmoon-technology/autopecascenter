<?php

namespace App\Mail\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldQueue: Mailer::sendMailable() detecta essa interface sozinho e troca
 * o envio síncrono por queue() — o call site (User::sendPasswordResetNotification())
 * não precisa mudar. Tira o "esqueci minha senha" do caminho crítico da
 * instabilidade do provedor de e-mail (ex: Resend fora do ar) e ganha o
 * retry automático já configurado no worker (--tries=3), em vez de a
 * requisição do usuário devolver 500 nesse cenário.
 */
class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $url;

    public readonly int $expiration;

    public function __construct(
        public readonly User $user,
        string $token,
    ) {
        $this->url = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $this->expiration = config('auth.passwords.users.expire');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Redefinição de senha — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.reset-password',
        );
    }
}
