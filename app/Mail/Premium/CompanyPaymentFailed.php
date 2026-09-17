<?php

declare(strict_types=1);

namespace App\Mail\Premium;

use App\Models\Subscription;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail 'Zahlung fehlgeschlagen' fuer Pro/Premium-Betriebe (#5).
 * Layout: mail.sun.layout (#16); der Tenant geht als mailTenant mit,
 * weil der Versand im Central-Kontext laeuft.
 */
class CompanyPaymentFailed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Zahlung fehlgeschlagen – dein :plan-Paket', ['plan' => $this->subscription->plan?->name]),
        );
    }

    public function content(): Content
    {
        $tenant = $this->subscription->tenant;

        return new Content(
            view: 'emails.premium.payment-failed',
            with: [
                'mailTenant' => $tenant,
                'graceDays' => (int) config('premium.grace_period_days', 7),
                'billingUrl' => TenantMailBranding::for($tenant)->url('/firmenprofil/premium'),
            ],
        );
    }
}
