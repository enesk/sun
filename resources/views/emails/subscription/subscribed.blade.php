{{-- Abo aktiviert (Willkommensmail), umgestellt auf das sun-Mail-Layout (#16). Der Tenant kommt aus dem Abo, weil der Versand im Central-Kontext laeuft. --}}
@extends('mail.sun.layout')

@php
    $mailTenant = $subscription->tenant;
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $contactEmail = (string) ($mailTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('app.support_email'));
    $contract = config('premium.contract');

    // Preise sind netto (config/premium.php), Betraege in Cent. Deutsch formatiert,
    // weil @money die App-Locale (en) nutzt.
    $currencyCode = $subscription->currency->code ?? config('premium.currency', 'EUR');
    $euro = fn ($cents) => \Illuminate\Support\Number::currency(((int) $cents) / 100, in: $currencyCode, locale: 'de');
    $vatPercent = (int) $contract['vat_percent'];
    $net = (int) $subscription->price;
    $vat = (int) round($net * $vatPercent / 100);
    $gross = $net + $vat;
    $intervalName = $subscription->interval?->name;
    $intervalLabel = $intervalName ? __("premium.subscription.intervals.{$intervalName}") : null;
    // Zusammengesetzt, weil Blade ein @if direkt hinter einem Wort nicht erkennt
    $perInterval = $intervalLabel ? ' je '.$intervalLabel : '';
@endphp

@section('preview')
    Willkommen bei {{ $portalName }}!
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Willkommen bei {{ $portalName }}!
    </h1>
    <p style="margin: 0; color: #3f3f46;">
        Hallo {{ $subscription->user->name }},
        <br>
        <br>
        danke für deine Buchung! Dein Paket <strong>{{ $subscription->plan->name }}</strong> ist ab sofort aktiv. Das steht dir jetzt zur Verfügung:
    </p>

    <ul style="margin: 16px 0 0; padding-left: 20px; line-height: 28px; color: #3f3f46;">
        <li>Werbefreies Profil</li>
        <li>Fotogalerie im Profil</li>
        <li>Antworten auf Bewertungen</li>
        <li>Top-Platzierung in der Suche</li>
        <li>Exklusive Anfragen</li>
        <li>Statistiken zu deinem Profil</li>
        <li>Verifiziert-Badge</li>
    </ul>

    @php
        $tenantDomain = $subscription->tenant?->domains?->first()?->domain;
        $dashboardUrl = $tenantDomain
            ? 'https://' . $tenantDomain . '/firmenprofil/bearbeiten'
            : url('/firmenprofil/bearbeiten');
    @endphp

    @if ($net > 0)
        <div role="separator" style="background-color: #e4e4e7; height: 1px; line-height: 1px; margin: 24px 0;">&zwj;</div>

        <table cellpadding="0" cellspacing="0" role="none" style="width: 100%;">
            <tr>
                <td align="left" style="padding-bottom: 4px; color: #3f3f46;">Preis netto{{ $perInterval }}</td>
                <td align="right" style="padding-bottom: 4px; color: #3f3f46;">{{ $euro($net) }}</td>
            </tr>
            <tr>
                <td align="left" style="padding-bottom: 8px; color: #3f3f46;">zzgl. {{ $vatPercent }} % USt.</td>
                <td align="right" style="padding-bottom: 8px; color: #3f3f46;">{{ $euro($vat) }}</td>
            </tr>
            <tr>
                <td align="left" style="border-top: 1px solid #e4e4e7; padding-top: 8px; font-weight: 600; color: #18181b;">Gesamt brutto{{ $perInterval }}</td>
                <td align="right" style="border-top: 1px solid #e4e4e7; padding-top: 8px; font-weight: 600; color: #18181b;">{{ $euro($gross) }}</td>
            </tr>
        </table>

        <div role="separator" style="background-color: #e4e4e7; height: 1px; line-height: 1px; margin: 24px 0;">&zwj;</div>
    @endif

    @include('mail.sun.partials.button', ['url' => $dashboardUrl, 'label' => 'Firmenprofil bearbeiten'])

    <p style="margin: 32px 0 0; font-size: 14px; line-height: 22px; color: #71717a;">
        Zu deinem Vertrag: Die Laufzeit beträgt {{ $contract['term_months'] }} Monate, die Kündigungsfrist {{ $contract['notice_months'] }} Monate zum Laufzeitende. Alle Preise verstehen sich netto zzgl. {{ $contract['vat_percent'] }} % USt.
    </p>

    @if ($contactEmail !== '')
        <p style="margin: 16px 0 0; font-size: 14px; line-height: 22px; color: #71717a;">
            Du hast Fragen? Schreib uns einfach:
            <a href="mailto:{{ $contactEmail }}" style="color: #71717a;">{{ $contactEmail }}</a>
        </p>
    @endif

    <p style="margin: 24px 0 0; color: #3f3f46;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>
@endsection
