{{-- Uebernahme freigegeben, mit Zugang (App\ProfileRequests\Mail\ClaimApprovedMail, SUN-RQ #10).
     Sie-Form, sun-Mail-Layout, Branding aus dem Tenant der Mail.

     Kein Passwort in dieser Mail: bei einem neuen Konto verschickt
     ApproveClaim (#6) den Passwort-Link von Laravel separat. --}}
@extends('mail.sun.layout')

@php
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
@endphp

@section('preview')
    {{ $companyName !== '' ? $companyName : 'Ihr Eintrag' }} gehört jetzt Ihnen
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ihr Eintrag gehört jetzt Ihnen
    </h1>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        @if ($profileRequest->requester_name)
            Guten Tag {{ $profileRequest->requester_name }},
        @else
            Guten Tag,
        @endif
    </p>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        wir haben Ihnen den Eintrag
        <strong>{{ $companyName !== '' ? $companyName : 'Ihres Betriebs' }}</strong> auf
        {{ $portalName }} zugeordnet. Sie können ihn ab jetzt selbst pflegen.
    </p>

    @if ($accountCreated)
        <p style="margin: 0 0 16px; color: #3f3f46;">
            Dafür haben wir ein Konto für Sie angelegt. In einer zweiten E-Mail finden Sie einen Link,
            über den Sie Ihr Passwort selbst setzen — wir legen keines für Sie fest. Danach melden Sie
            sich mit Ihrer E-Mail-Adresse und diesem Passwort an.
        </p>
    @else
        <p style="margin: 0 0 16px; color: #3f3f46;">
            Melden Sie sich einfach mit Ihrer bekannten E-Mail-Adresse und Ihrem Passwort an. Falls Sie
            es nicht mehr wissen, können Sie es auf der Anmeldeseite neu setzen.
        </p>
    @endif

    @include('mail.sun.partials.button', [
        'url' => $loginUrl,
        'label' => 'Zum Eintrag',
    ])

    <h2 style="margin: 32px 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
        Was Sie jetzt tun können
    </h2>
    <ul style="margin: 0 0 16px; padding-left: 20px; color: #3f3f46;">
        <li style="margin-bottom: 6px;">Kontaktdaten, Öffnungszeiten und Beschreibung korrigieren</li>
        <li style="margin-bottom: 6px;">Logo und Bilder hochladen</li>
        <li style="margin-bottom: 6px;">Auf Bewertungen antworten</li>
        <li style="margin-bottom: 6px;">Anfragen von Kunden sehen, die über das Portal kommen</li>
    </ul>

    @include('emails.profile-requests.partials.footer')
@endsection
