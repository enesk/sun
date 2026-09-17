{{-- Neue Registrierung: Hinweis an den Portalbetreiber (App\Mail\NewRegistrationNotification). Texte stehen fest in der View, sun-Mail-Layout (SUN-PREM-015, #16). --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Neue Registrierung: {{ $user->name }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Neue Registrierung
    </h1>
    <p style="margin: 0 0 24px;">
        Auf {{ $portalName }} hat sich jemand neu registriert. Hier die Details:
    </p>

    <table style="width: 100%; margin-bottom: 24px;" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">Name:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $user->name }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">E-Mail:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $user->email }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Tenant:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ tenant()?->name ?? tenant()?->id ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Zeitpunkt:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $user->created_at->format('d.m.Y H:i') }} Uhr</td>
        </tr>
    </table>

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Automatische Nachricht von {{ $portalName }}.
    </p>
@endsection
