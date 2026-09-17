{{-- Paket ausgelaufen (locally managed subscription), umgestellt auf das sun-Mail-Layout (#16). Der Tenant kommt aus dem Abo, weil der Versand im Central-Kontext laeuft. --}}
@extends('mail.sun.layout')

@php
    $mailTenant = $subscription->tenant;
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $contactEmail = (string) ($mailTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('app.support_email'));
    $contract = config('premium.contract');
@endphp

@section('preview')
    Dein Paket bei {{ $portalName }} ist ausgelaufen
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Dein Paket ist ausgelaufen
    </h1>
    <p style="margin: 0; color: #3f3f46;">
        Hallo {{ $subscription->user->name }},
        <br>
        <br>
        dein Paket <strong>{{ $subscription->plan->name ?? 'Premium' }}</strong> ist heute ausgelaufen. Dein Profil läuft ab jetzt als Basis-Eintrag weiter.
    </p>

    <p style="margin: 16px 0 0; color: #3f3f46;">
        Deine Inhalte (Fotos, Antworten, Beschreibungen) bleiben gespeichert — sie werden nur nicht mehr öffentlich angezeigt, bis du wieder buchst.
    </p>

    <p style="margin: 16px 0 0; font-weight: 600; color: #18181b;">
        Das bekommst du damit zurück:
    </p>
    <ul style="margin: 8px 0 0; padding-left: 20px; line-height: 28px; color: #3f3f46;">
        <li>Werbefreies Profil</li>
        <li>Fotogalerie im Profil</li>
        <li>Antworten auf Bewertungen</li>
        <li>Top-Platzierung in der Suche</li>
        <li>Exklusive Anfragen</li>
        <li>Statistiken zu deinem Profil</li>
        <li>Verifiziert-Badge</li>
    </ul>

    @include('mail.sun.partials.button', [
        'url' => central_route('checkout.convert-local-subscription', ['subscriptionUuid' => $subscription->uuid]),
        'label' => 'Paket ansehen',
    ])

    <p style="margin: 32px 0 0; font-size: 14px; line-height: 22px; color: #71717a;">
        Die Laufzeit beträgt {{ $contract['term_months'] }} Monate, die Kündigungsfrist {{ $contract['notice_months'] }} Monate zum Laufzeitende. Alle Preise verstehen sich netto zzgl. {{ $contract['vat_percent'] }} % USt.
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
