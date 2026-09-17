<?php

declare(strict_types=1);

namespace App\Mail\Company;

use App\Models\Portal\CompanyLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Neue exklusive Anfrage an den Betrieb (#9), mit vollstaendigen Kontaktdaten.
 * Laeuft ueber die Queue; der Tenant reist per QueueTenancyBootstrapper mit.
 * Layout: mail.premium.layout (#16).
 */
class NewExclusiveLeadMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CompanyLead $lead,
        public string $companyName,
        public string $dashboardUrl,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('portal.owner.leads.mail.subject', ['name' => $this->lead->contact_name ?: __('portal.owner.inquiries.unknown_name')]),
            replyTo: $this->lead->contact_email ? [$this->lead->contact_email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.company.new-exclusive-lead',
        );
    }
}
