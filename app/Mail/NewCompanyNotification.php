<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Hinweis an die Administratoren: auf einem Portal wurde eine Firma selbst
 * eingetragen. Traegt nur fertige Werte, damit die Mail in der Queue ohne
 * Tenant-Kontext gerendert werden kann.
 */
class NewCompanyNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string|null>  $details
     */
    public function __construct(
        public array $details,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Neue Firma eingetragen: {$this->details['company']} ({$this->details['portal']})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-company-notification',
        );
    }
}
