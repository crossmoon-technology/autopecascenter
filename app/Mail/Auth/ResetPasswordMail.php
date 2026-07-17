<?php

namespace App\Mail\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
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
            subject: 'Redefinição de senha — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.reset-password',
        );
    }
}
