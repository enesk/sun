<?php

declare(strict_types=1);

namespace App\Mail\Company;

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Services\CompanyUrlService;
use App\Support\Tenancy\TenantMailBranding;
use App\Support\Tenancy\TenantPremiumPricing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Eintrag ist freigeschaltet: Nachricht an den Inhaber, mit Hinweis auf die
 * exklusiven Anfragen im Premium-Paket. Layout: mail.sun.layout (#16).
 */
class CompanyActivatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public ?Tenant $mailTenant = null,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $branding = TenantMailBranding::for($this->mailTenant);

        return new Envelope(
            subject: $this->company->name.' ist jetzt auf '.$branding->portalName().' freigeschaltet',
        );
    }

    public function content(): Content
    {
        $branding = TenantMailBranding::for($this->mailTenant);
        $pricing = TenantPremiumPricing::for($this->mailTenant);

        return new Content(
            view: 'emails.company.company-activated',
            with: [
                'mailTenant' => $this->mailTenant,
                'profileUrl' => $this->profileUrl($branding),
                'dashboardUrl' => $branding->url('/firmenprofil'),
                'premiumUrl' => $branding->url('/premium'),
                // Upsell nur, wenn das Portal Premium ueberhaupt verkauft.
                'premiumSaleEnabled' => $pricing->isSaleEnabled(),
                'premiumFromCents' => $this->cheapestMonthlyCents($pricing),
                // Preise sind netto, Mindestlaufzeit 12 Monate (config/premium.php)
                'vatPercent' => (int) config('premium.contract.vat_percent', 19),
                'termMonths' => (int) config('premium.contract.term_months', 12),
            ],
        );
    }

    private function profileUrl(TenantMailBranding $branding): string
    {
        // Ueber die Tenant-Domain, weil route() in der Queue nicht auf das
        // Portal zeigt (siehe TenantMailBranding).
        return $branding->url(CompanyUrlService::path($this->company));
    }

    private function cheapestMonthlyCents(TenantPremiumPricing $pricing): ?int
    {
        $candidates = collect(TenantPremiumPricing::subscriptionKeys())
            ->filter(fn (string $key): bool => str_ends_with($key, '_monthly') && $pricing->isAvailable($key))
            ->map(fn (string $key): ?int => $pricing->grossCents($key))
            ->filter()
            ->values();

        return $candidates->min();
    }
}
