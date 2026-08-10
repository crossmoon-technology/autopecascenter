<?php

namespace App\Mail\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * ShouldQueue: Mailer::sendMailable() detecta essa interface sozinho e troca
 * o envio síncrono por queue() — os call sites (Mail::to()->send(...)) não
 * precisam mudar. Tira o registro/login do caminho crítico da instabilidade
 * do provedor de e-mail (ex: Resend fora do ar) e ganha o retry automático já
 * configurado no worker (--tries=3), em vez de a requisição do usuário
 * devolver 500 nesse cenário.
 */
class VerifyEmailMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $url;

    public readonly int $expiration;

    public function __construct(
        public readonly User $user,
    ) {
        $this->expiration = 60;

        $this->url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($this->expiration),
            ['id' => $user->id],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu e-mail — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify-email',
        );
    }
}
