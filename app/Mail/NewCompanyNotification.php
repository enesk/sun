<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Hinweis an die Administratoren: auf einem Portal wurde eine Firma selbst
 * eingetragen. Traegt nur fertige Werte, Texte kommen aus portal.mail.* (#11).
 * Die Mail wird im Tenant-Kontext eingereiht; der QueueTenancyBootstrapper
 * stellt ihn im Worker wieder her, dort setzt der TenantTranslator Portalname
 * und Branchenbegriffe des ausloesenden Tenants ein.
 */
class NewCompanyNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string|bool|null>  $details
     */
    public function __construct(
        public array $details,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), __('portal.mail.from_name')),
            subject: __('portal.mail.new_company.subject', ['firma' => $this->details['company']]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-company-notification',
        );
    }
}
