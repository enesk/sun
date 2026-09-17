<?php

namespace App\Mail\Subscription;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpired extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Paket bei '.$this->portalName().' ist ausgelaufen',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.trial-expired',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Portalname des Abos; ohne Tenant der App-Name (Mails aus dem Central-Kontext).
     */
    private function portalName(): string
    {
        return \App\Support\Tenancy\TenantMailBranding::for($this->subscription->tenant ?? null)->portalName();
    }
}
