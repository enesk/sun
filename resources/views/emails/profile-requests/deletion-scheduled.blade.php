{{-- Loeschung angekuendigt, Karenzfrist laeuft
     (App\ProfileRequests\Mail\DeletionScheduledMail, SUN-RQ #10).
     Sie-Form, sun-Mail-Layout, Branding aus dem Tenant der Mail.

     Die wichtigste der sieben Mails: sie ist die letzte Gelegenheit zu
     widersprechen. Darum steht der Termin fett im ersten Absatz und der
     Stornoknopf vor allem anderen — nicht am Ende. --}}
@extends('mail.sun.layout')

@php
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $frist = $graceUntil->timezone('Europe/Berlin');
@endphp

@section('preview')
    Löschung am {{ $frist->format('d.m.Y') }} — bis dahin können Sie widersprechen
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ihr Eintrag wird gelöscht
    </h1>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        @if ($profileRequest->requester_name)
            Guten Tag {{ $profileRequest->requester_name }},
        @else
            Guten Tag,
        @endif
    </p>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        Ihr Löschantrag für <strong>{{ $companyName !== '' ? $companyName : 'Ihren Eintrag' }}</strong>
        auf {{ $portalName }} ist bestätigt. Wir löschen den Eintrag am
        <strong>{{ $frist->format('d.m.Y') }} um {{ $frist->format('H:i') }} Uhr</strong>.
    </p>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        Bis dahin passiert nichts Unwiderrufliches. Wenn der Antrag ein Versehen war oder von jemandem
        kam, der nicht für Sie sprechen durfte, stoppen Sie die Löschung hier:
    </p>

    @include('mail.sun.partials.button', [
        'url' => $statusUrl,
        'label' => 'Löschung stornieren',
    ])

    <p style="margin: 8px 0 24px; font-size: 14px; color: #71717a;">
        Der Knopf führt auf die Statusseite dieses Vorgangs. Dort steht auch, was eingereicht wurde.
    </p>

    <h2 style="margin: 0 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
        Was gelöscht wird
    </h2>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Das Profil mit Adresse und Kontaktdaten, Logo und alle Bilder, Bewertungen samt Ihrer
        Antworten, Öffnungszeiten, Leistungen und Statistiken. Die Profilseite ist danach nicht mehr
        erreichbar. Kontaktdaten aus früheren Anfragen werden anonymisiert; die Anfragen selbst
        bleiben für die Abrechnung erhalten, ohne Personenbezug.
    </p>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Zurückholen lässt sich das nicht. Sie können sich später jederzeit neu eintragen, dann
        allerdings ohne die alten Bewertungen und Bilder.
    </p>

    @include('emails.profile-requests.partials.footer', ['withStatusLink' => false])
@endsection
