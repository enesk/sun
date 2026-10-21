{{-- Aenderung uebernommen (App\ProfileRequests\Mail\ChangesAppliedMail, SUN-RQ #10).
     Sie-Form, sun-Mail-Layout, Branding aus dem Tenant der Mail.

     Zeigt getrennt, was im Profil steht und was noch offen ist: das Panel
     (#11) und die Auto-Regel (#9) uebernehmen zeilenweise. --}}
@extends('mail.sun.layout')

@php
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
@endphp

@section('preview')
    Ihre Änderung steht im Eintrag {{ $companyName !== '' ? $companyName : '' }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ihre Änderung ist übernommen
    </h1>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        @if ($profileRequest->requester_name)
            Guten Tag {{ $profileRequest->requester_name }},
        @else
            Guten Tag,
        @endif
    </p>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        wir haben Ihre Angaben in den Eintrag
        <strong>{{ $companyName !== '' ? $companyName : 'Ihres Betriebs' }}</strong> auf
        {{ $portalName }} übernommen.
    </p>

    @if (! empty($applied))
        <p style="margin: 0 0 8px; color: #3f3f46;">Geändert haben wir:</p>
        <ul style="margin: 0 0 24px; padding-left: 20px; color: #3f3f46;">
            @foreach ($applied as $label)
                <li style="margin-bottom: 6px;">{{ $label }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($pending))
        <h2 style="margin: 0 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
            Noch offen
        </h2>
        <p style="margin: 0 0 8px; color: #3f3f46;">
            Diese Angaben sehen wir uns noch an — sie betreffen Felder, die wir nicht ohne Prüfung
            ändern, etwa Firmenname, Adresse oder Kategorie:
        </p>
        <ul style="margin: 0 0 24px; padding-left: 20px; color: #3f3f46;">
            @foreach ($pending as $label)
                <li style="margin-bottom: 6px;">{{ $label }}</li>
            @endforeach
        </ul>
        <p style="margin: 0 0 16px; color: #3f3f46;">
            Sie müssen dafür nichts tun. Sobald das entschieden ist, schreiben wir Ihnen.
        </p>
    @endif

    @if ($profileUrl !== null)
        @include('mail.sun.partials.button', [
            'url' => $profileUrl,
            'label' => 'Eintrag ansehen',
        ])
    @endif

    <p style="margin: 24px 0 0; color: #3f3f46;">
        Bis Suchmaschinen den neuen Stand zeigen, kann es einige Tage dauern — bei uns ist er sofort
        sichtbar.
    </p>

    @include('emails.profile-requests.partials.footer')
@endsection
