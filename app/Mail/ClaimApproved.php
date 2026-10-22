<?php

namespace App\Mail;

use App\Claims\ClaimNotificationStatus;
use App\Models\Portal\ClaimRequest;
use App\Models\Tenant;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Freigabe eines Claim-Antrags: Nachricht an den Antragsteller.
 * Gegenstueck zu ClaimRequestNotification (die geht an den Betreiber).
 */
class ClaimApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Kopfzeilen, an denen der Versandstatus den Antrag wiederfindet (#25). */
    public const HEADER_CLAIM = 'X-Sun-Claim-Request';

    public const HEADER_TENANT = 'X-Sun-Claim-Tenant';

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

    /**
     * Kennzeichnung fuer den Versandstatus (#25): das Mail-Ereignis im
     * Warteschlangen-Arbeiter kennt die Mailable nicht mehr, nur die
     * Nachricht. Antrag und Portal reisen deshalb als Kopfzeilen mit und
     * werden von {@see \App\Listeners\Claims\RecordClaimNotificationSent}
     * wieder ausgelesen.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            self::HEADER_CLAIM => (string) $this->claimRequest->getKey(),
            self::HEADER_TENANT => (string) $this->tenant->getKey(),
        ]);
    }

    /**
     * Der Job ist endgueltig gescheitert (Laravel ruft das ueber
     * SendQueuedMailable::failed auf). Der Grund gehoert an den Antrag, sonst
     * steht im Panel "genehmigt" und niemand weiss, dass die Nachricht nie
     * ankam.
     */
    public function failed(Throwable $e): void
    {
        ClaimNotificationStatus::markFailed(
            $this->tenant,
            (int) $this->claimRequest->getKey(),
            'Versand gescheitert: '.$e->getMessage(),
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
