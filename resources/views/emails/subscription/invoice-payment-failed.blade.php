{{-- Zahlungsproblem beim Abo, umgestellt auf das sun-Mail-Layout (#16). Der Tenant kommt aus dem Abo, weil der Versand im Central-Kontext laeuft. --}}
@extends('mail.sun.layout')

@php
    $mailTenant = $subscription->tenant;
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $contactEmail = (string) ($mailTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('app.support_email'));
@endphp

@section('preview')
    Zahlung fehlgeschlagen — bitte Zahlungsdaten prüfen
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Zahlung fehlgeschlagen
    </h1>
    <p style="margin: 0; color: #3f3f46;">
        Hallo {{ $subscription->user->name }},
        <br>
        <br>
        die Zahlung für dein Paket <strong>{{ $subscription->plan->name }}</strong> konnte nicht eingezogen werden. Bitte prüf deine Zahlungsdaten, damit dein Profil ohne Unterbrechung online bleibt. Deine Inhalte bleiben dabei in jedem Fall gespeichert.
    </p>

    @php
        $tenantDomain = $subscription->tenant?->domains?->first()?->domain;
        $billingUrl = $tenantDomain
            ? 'https://' . $tenantDomain . '/verwaltung/abonnements'
            : url('/verwaltung/abonnements');
    @endphp

    @include('mail.sun.partials.button', ['url' => $billingUrl, 'label' => 'Zahlungsdaten prüfen'])

    @if ($contactEmail !== '')
        <p style="margin: 32px 0 0; font-size: 14px; line-height: 22px; color: #71717a;">
            Du hast Fragen? Schreib uns einfach:
            <a href="mailto:{{ $contactEmail }}" style="color: #71717a;">{{ $contactEmail }}</a>
        </p>
    @endif

    <p style="margin: 24px 0 0; color: #3f3f46;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>
@endsection
