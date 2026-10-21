{{-- Bestaetigungsmail mit Magic-Link (App\ProfileRequests\Mail\VerifyRequestMail, SUN-RQ #6).
     Sie-Form, Texte stehen fest in der View, sun-Mail-Layout.
     Geht nur an die beim Profil hinterlegte Firmen-E-Mail.
     Betreff, Message-ID, Antwortadresse und Fuss aus der gemeinsamen Basis (#10).
     Wortlaut zur Abnahme in docs/profile-requests.md 11d. --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
@endphp

@section('preview')
    Bitte bestätigen Sie die Anfrage zu {{ $companyName !== '' ? $companyName : 'Ihrem Eintrag' }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Bitte bestätigen Sie diese Anfrage
    </h1>

    <p style="margin: 0 0 16px; color: #3f3f46;">Guten Tag,</p>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        {{ $what }}
        @if ($companyName !== '')
            Betroffen ist Ihr Eintrag <strong>{{ $companyName }}</strong> auf {{ $portalName }}.
        @else
            Betroffen ist Ihr Eintrag auf {{ $portalName }}.
        @endif
    </p>

    @if (! empty($changes))
        <p style="margin: 0 0 8px; color: #3f3f46;">Gewünscht ist:</p>
        <ul style="margin: 0 0 16px; padding-left: 20px; color: #3f3f46;">
            @foreach ($changes as $line)
                <li style="margin-bottom: 6px;">{{ $line }}</li>
            @endforeach
        </ul>
    @endif

    <p style="margin: 0 0 8px; color: #3f3f46;">
        Wir schreiben an diese Adresse, weil sie in Ihrem Eintrag hinterlegt ist. Erst mit Ihrer
        Bestätigung bearbeiten wir die Anfrage — ohne sie passiert nichts.
    </p>

    @include('mail.sun.partials.button', [
        'url' => $verifyUrl,
        'label' => 'Anfrage bestätigen',
    ])

    <p style="margin: 8px 0 24px; font-size: 14px; color: #71717a;">
        Der Link ist bis zum {{ $expiresAt->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr gültig.
    </p>

    <h2 style="margin: 0 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
        Sie haben die Anfrage nicht gestellt?
    </h2>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Dann bestätigen Sie nichts, sondern widersprechen Sie hier — wir legen die Anfrage dann ohne
        weitere Rückfrage zu den Akten:
        <a href="{{ $cancelUrl }}" style="color: {{ $branding->brandColor() }};">Anfrage ablehnen</a>
    </p>

    @include('emails.profile-requests.partials.footer')
@endsection
