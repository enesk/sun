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
 * Freigabe eines Claim-Antrags: Nachricht an den Antragsteller.
 * Gegenstueck zu ClaimRequestNotification (die geht an den Betreiber).
 */
class ClaimApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClaimRequest $claimRequest,
        public Tenant $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Eintrag bei '.TenantMailBranding::for($this->tenant)->portalName().' ist freigeschaltet',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.claim-approved',
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
