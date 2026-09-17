<?php

namespace App\Mail;

use App\Models\Portal\ClaimRequest;
use App\Models\Tenant;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Ablehnung eines Claim-Antrags: Nachricht an den Antragsteller.
 * Gegenstueck zu ClaimApproved.
 */
class ClaimRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClaimRequest $claimRequest,
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Antrag bei '.TenantMailBranding::for($this->tenant)->portalName().' konnte nicht freigegeben werden',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.claim-rejected',
            with: [
                'mailTenant' => $this->tenant,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
