<?php

namespace App\Mail;

use App\Models\Portal\ClaimRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClaimRequestNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClaimRequest $claimRequest,
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->claimRequest->company->name ?? 'Unbekannt';

        return new Envelope(
            subject: "Neuer Claim-Antrag: {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.claim-request-notification',
        );
    }
}
