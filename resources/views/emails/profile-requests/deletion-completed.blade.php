{{-- Loeschung erledigt: Bestaetigung an den Antragsteller (App\ProfileRequests\Mail\DeletionCompletedMail, #8).
     Sie-Form, Texte stehen fest in der View, sun-Mail-Layout.
     Betreff, Message-ID, Antwortadresse und Fuss aus der gemeinsamen Basis (#10).
     Wortlaut zur Abnahme in docs/profile-requests.md 11d. --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
@endphp

@section('preview')
    {{ $companyName }} ist gelöscht — was noch zu wissen ist
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ihr Eintrag wurde gelöscht
    </h1>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        @if ($profileRequest->requester_name)
            Guten Tag {{ $profileRequest->requester_name }},
        @else
            Guten Tag,
        @endif
    </p>

    <p style="margin: 0 0 24px; color: #3f3f46;">
        wir haben Ihren Eintrag <strong>{{ $companyName }}</strong> auf {{ $portalName }} gelöscht.
        Das ist abgeschlossen und muss von Ihnen nicht weiter verfolgt werden.
    </p>

    @if (! empty($deletedKinds))
        <p style="margin: 0 0 8px; color: #3f3f46;">Gelöscht wurden:</p>
        <ul style="margin: 0 0 24px; padding-left: 20px; color: #3f3f46;">
            @foreach ($deletedKinds as $kind)
                <li style="margin-bottom: 6px;">{{ $kind }}</li>
            @endforeach
        </ul>
    @endif

    <h2 style="margin: 0 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
        Warum Google die Seite noch anzeigt
    </h2>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Bei uns ist die Seite sofort weg. Google und andere Suchmaschinen haben eine eigene Kopie
        und entfernen sie erst, wenn sie die Adresse das nächste Mal abrufen — das dauert in der
        Regel einige Tage bis wenige Wochen. Wir haben die Löschung bereits gemeldet; beschleunigen
        lässt sie sich von unserer Seite nicht weiter.
    </p>
    <p style="margin: 0 0 24px; color: #3f3f46;">
        Wenn es Ihnen schneller gehen muss, können Sie die Adresse bei Google selbst zur Entfernung
        einreichen:
        <a href="https://search.google.com/search-console/remove-outdated-content" style="color: {{ $branding->brandColor() }};">
            Veraltete Inhalte entfernen
        </a>
    </p>

    <h2 style="margin: 0 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
        Wenn Sie es sich anders überlegen
    </h2>
    <p style="margin: 0 0 24px; color: #3f3f46;">
        Sie können sich jederzeit neu eintragen — die Adresse ist dafür nicht gesperrt.
        Die alten Daten, Bilder und Bewertungen sind allerdings gelöscht und lassen sich
        nicht zurückholen.
    </p>

    @include('emails.profile-requests.partials.footer')
@endsection
