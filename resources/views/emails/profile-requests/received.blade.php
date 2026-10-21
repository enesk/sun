{{-- Eingangsbestaetigung (App\ProfileRequests\Mail\RequestReceivedMail, SUN-RQ #10).
     Sie-Form, sun-Mail-Layout, Branding aus dem Tenant der Mail.
     Geht an den Anfragenden — sie bestaetigt nichts und gibt nichts frei. --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
@endphp

@section('preview')
    Angekommen — Vorgang REQ-{{ $profileRequest->id }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ihre Anfrage ist angekommen
    </h1>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        @if ($profileRequest->requester_name)
            Guten Tag {{ $profileRequest->requester_name }},
        @else
            Guten Tag,
        @endif
    </p>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        {{ $what }}
        @if ($companyName !== '')
            Betroffen ist der Eintrag <strong>{{ $companyName }}</strong> auf {{ $portalName }}.
        @else
            Betroffen ist Ihr Eintrag auf {{ $portalName }}.
        @endif
    </p>

    @if (! empty($changes))
        <p style="margin: 0 0 8px; color: #3f3f46;">Das haben Sie eingereicht:</p>
        <ul style="margin: 0 0 16px; padding-left: 20px; color: #3f3f46;">
            @foreach ($changes as $line)
                <li style="margin-bottom: 6px;">{{ $line }}</li>
            @endforeach
        </ul>
    @endif

    <h2 style="margin: 24px 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
        Wie es weitergeht
    </h2>

    @if ($verificationSentTo !== null)
        <p style="margin: 0 0 16px; color: #3f3f46;">
            Zur Sicherheit haben wir eine Bestätigung an die im Eintrag hinterlegte Adresse
            <strong>{{ $verificationSentTo }}</strong> geschickt. Erst ein Klick auf den Link darin
            gibt die Anfrage frei — ohne ihn ändern wir nichts. Schauen Sie bitte auch im Spam-Ordner
            nach, wenn die E-Mail nicht ankommt.
        </p>
    @else
        <p style="margin: 0 0 16px; color: #3f3f46;">
            Beim Eintrag ist keine E-Mail-Adresse hinterlegt, über die wir Sie als Inhaber erkennen
            können. Wir prüfen die Anfrage deshalb von Hand und melden uns bei Ihnen.
        </p>
    @endif

    <p style="margin: 0 0 16px; color: #3f3f46;">
        Den Stand können Sie jederzeit selbst nachsehen — die Adresse gehört nur zu diesem Vorgang:
    </p>

    @include('mail.sun.partials.button', [
        'url' => $statusUrl,
        'label' => 'Stand der Anfrage ansehen',
    ])

    @include('emails.profile-requests.partials.footer', ['withStatusLink' => false])
@endsection
