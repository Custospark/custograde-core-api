<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Custograde verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.verification-code-text',
            with: [
                'code' => $this->code,
                'minutes' => 15,
            ],
        );
    }
}
