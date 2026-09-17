{{-- Claim-Antrag genehmigt: Nachricht an den Antragsteller (App\Mail\ClaimApproved). Texte stehen fest in der View, sun-Mail-Layout. --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $companyName = $claimRequest->company->name ?? '';
@endphp

@section('preview')
    {{ $companyName }} gehört jetzt dir — der Eintrag ist freigeschaltet
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Dein Eintrag ist freigeschaltet
    </h1>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Hallo {{ $claimRequest->user->name ?? '' }},
    </p>
    <p style="margin: 0 0 24px; color: #3f3f46;">
        wir haben deinen Antrag geprüft: <strong>{{ $companyName }}</strong> gehört jetzt dir.
        Du kannst den Eintrag ab sofort in deinem Betriebsbereich auf {{ $portalName }} bearbeiten.
    </p>

    @include('mail.sun.partials.button', [
        'url' => $branding->url('/firmenprofil'),
        'label' => 'Zum Betriebsbereich',
    ])

    <p style="margin: 24px 0 8px; color: #3f3f46;">Das lohnt sich als Erstes:</p>
    <ul style="margin: 0 0 24px; padding-left: 20px; color: #3f3f46;">
        <li style="margin-bottom: 6px;">Beschreibung, Öffnungszeiten und Kontaktdaten prüfen</li>
        <li style="margin-bottom: 6px;">Logo und Bilder hochladen</li>
        <li style="margin-bottom: 6px;">Leistungen und Kategorien ergänzen, damit du besser gefunden wirst</li>
        <li>Deine Kunden um eine Bewertung bitten</li>
    </ul>

    <p style="margin: 32px 0 0; color: #3f3f46;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => $portalName]) }}
    </p>
@endsection
