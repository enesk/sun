<?php

namespace App\Mail\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $url,
        public ?string $name = null,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Auf einem Portal (z. B. nach /eintragen) Texte und Absender aus portal.mail.* (#11)
        if (tenancy()->initialized) {
            return new Envelope(
                from: new Address((string) config('mail.from.address'), __('portal.mail.from_name')),
                subject: __('portal.mail.verify_email.subject'),
            );
        }

        return new Envelope(
            subject: 'Bestätige deine E-Mail-Adresse',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        if (tenancy()->initialized) {
            return new Content(
                view: 'emails.portal.verify-email',
                with: [
                    'mailTenant' => tenant(),
                ],
            );
        }

        return new Content(
            view: 'emails.user.verify-email',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
