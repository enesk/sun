{{--
    Einmal-Passwort zur Anmeldung (Spatie one-time-passwords), sun-Mail-Layout.
    Geht an Nutzer eines Portals; Portalname und Farbe kommen aus dem Tenant,
    ohne Portal aus config('app.platform_name').
--}}
@extends('mail.sun.layout')

@php
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Dein Einmal-Passwort für {{ $portalName }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Dein Einmal-Passwort
    </h1>
    <p style="margin: 0; color: #3f3f46;">
        Mit diesem Code meldest du dich bei {{ $portalName }} an:
    </p>

    <p style="margin: 24px 0; font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #18181b;">
        {{ $oneTimePassword->password }}
    </p>

    <p style="margin: 0; color: #3f3f46;">
        Gib den Code niemandem weiter. Hast du die Anmeldung nicht angefordert, kannst du diese Mail ignorieren.
    </p>

    <p style="margin: 32px 0 0; color: #3f3f46;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => $portalName]) }}
    </p>
@endsection
