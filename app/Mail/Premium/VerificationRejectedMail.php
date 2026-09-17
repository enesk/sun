<?php

declare(strict_types=1);

namespace App\Mail\Premium;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mail 'Nachweis abgelehnt' (#11). Bekommt nur Skalare, damit der Queue-Job
 * keine Tenant-Models ausserhalb des Tenant-Kontexts nachladen muss.
 * Layout: mail.sun.layout (#16).
 */
class VerificationRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public ?string $recipientName,
        public string $reason,
        public string $retryUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Dein Nachweis für :company wurde nicht bestätigt', ['company' => $this->companyName]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.premium.verification-rejected');
    }
}
