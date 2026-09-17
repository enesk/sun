{{-- Kuendigungsbestaetigung, umgestellt auf das sun-Mail-Layout (#16). Der Tenant kommt aus dem Abo, weil der Versand im Central-Kontext laeuft. --}}
@extends('mail.sun.layout')

@php
    $mailTenant = $subscription->tenant;
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $contactEmail = (string) ($mailTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('app.support_email'));
@endphp

@section('preview')
    Deine Kündigung bei {{ $portalName }} ist bestätigt
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Hallo {{ $subscription->user->name }},
    </h1>
    <p style="margin: 0; color: #3f3f46;">
        wir haben deine Kündigung erhalten und bestätigen sie hiermit. Dein Paket und alle Vorteile bleiben bis zum Ende der Laufzeit aktiv — danach läuft dein Profil als Basis-Eintrag weiter.
    </p>

    <p style="margin: 16px 0 0; color: #3f3f46;">
        Deine Inhalte bleiben gespeichert. Du kannst dein Paket jederzeit in deiner Verwaltung wieder buchen.
    </p>

    @if ($contactEmail !== '')
        <p style="margin: 16px 0 0; color: #3f3f46;">
            Sag uns gern, was wir besser machen können:
            <a href="mailto:{{ $contactEmail }}" style="color: #3f3f46;">{{ $contactEmail }}</a>
        </p>
    @endif

    <p style="margin: 16px 0 0; color: #3f3f46;">
        Danke, dass du dabei warst. Wir freuen uns, wenn wir dich wiedersehen!
    </p>

    <p style="margin: 32px 0 0; color: #3f3f46;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>
@endsection
