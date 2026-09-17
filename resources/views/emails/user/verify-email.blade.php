{{--
    E-Mail-Bestaetigung ausserhalb eines Portals (App\Mail\User\VerifyEmail im Central-Kontext).
    Empfaenger: neu registrierte Nutzerin/Nutzer. Texte fest im View (kein Sprachschluessel),
    Layout: mail.sun.layout; ohne Tenant greift der Fallback auf App-Namen und Standardfarbe.
--}}
@extends('mail.sun.layout')

@php
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Bestätige deine E-Mail-Adresse bei {{ $portalName }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Bestätige deine E-Mail-Adresse
    </h1>
    <p style="margin: 0 0 16px;">
        {{ filled($name ?? null) ? 'Hallo '.$name.',' : 'Hallo,' }}
    </p>
    <p style="margin: 0;">
        schön, dass du bei {{ $portalName }} dabei bist. Bestätige bitte noch deine E-Mail-Adresse – ein Klick auf den Button genügt.
    </p>

    @include('mail.sun.partials.button', ['url' => $url, 'label' => 'E-Mail bestätigen'])

    <p style="margin: 32px 0 16px;">
        Du hast kein Konto angelegt? Dann kannst du diese E-Mail einfach ignorieren.
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
