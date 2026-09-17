{{--
    Passwort zuruecksetzen (App\Mail\User\ResetPassword, ausgeloest ueber AuthServiceProvider).
    Empfaenger: Nutzerin/Nutzer, die/der eine Zuruecksetzung angefordert hat. Texte fest im View.
    Layout: mail.sun.layout; ohne bekannten Tenant greift der Fallback auf App-Namen und Standardfarbe.
--}}
@extends('mail.sun.layout')

@php
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Setz dein Passwort zurück
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Passwort zurücksetzen
    </h1>
    <p style="margin: 0 0 16px;">
        Hallo,
    </p>
    <p style="margin: 0;">
        für dein Konto bei {{ $portalName }} wurde ein neues Passwort angefordert. Über den Button vergibst du es in wenigen Sekunden.
    </p>

    @include('mail.sun.partials.button', ['url' => $url, 'label' => 'Neues Passwort vergeben'])

    <p style="margin: 32px 0 16px;">
        Der Link ist 60 Minuten gültig. Warst du das nicht, kannst du diese E-Mail einfach ignorieren – dein Passwort bleibt dann unverändert.
    </p>
    <p style="margin: 0;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>

    <div role="separator" style="background-color: #e4e4e7; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>

    <p style="margin: 0; font-size: 14px; line-height: 20px; color: #71717a;">
        Falls der Button nicht funktioniert, kopier diesen Link in deinen Browser: <a href="{{ $url }}" style="color: #71717a;">{{ $url }}</a>
    </p>
@endsection
