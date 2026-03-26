<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public string $userName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifica tu cuenta - Programmers',
        );
    }

    public function content(): Content
    {
        $verificationUrl = config('app.frontend_url', 'http://localhost:5173') . '/verify-email?token=' . $this->token;

        return new Content(
            markdown: 'emails.verify-email',
            with: [
                'url' => $verificationUrl,
                'userName' => $this->userName,
            ],
        );
    }
}
