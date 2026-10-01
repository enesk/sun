{{-- Eintrag freigeschaltet: Nachricht an den Inhaber (App\Mail\Company\CompanyActivatedMail).
     Texte stehen fest in der View, sun-Mail-Layout (#16). Der Premium-Block erscheint nur,
     wenn das Portal Premium verkauft ($premiumSaleEnabled). --}}
@extends('mail.sun.layout')

@php
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $brandColor = $branding->brandColor();
    $fromPrice = $premiumFromCents ? number_format($premiumFromCents / 100, 2, ',', '.').' € im Monat' : null;
@endphp

@section('preview')
    {{ $company->name }} ist ab sofort auf {{ $portalName }} zu finden
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Dein Eintrag ist freigeschaltet
    </h1>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Hallo {{ $company->owner->name ?? '' }},
    </p>
    <p style="margin: 0 0 8px; color: #3f3f46;">
        wir haben deinen Eintrag geprüft: <strong>{{ $company->name }}</strong> ist ab sofort auf
        {{ $portalName }} öffentlich zu finden — in der Suche, in deiner Stadt und in deinen Kategorien.
    </p>

    @include('mail.sun.partials.button', [
        'url' => $profileUrl,
        'label' => 'Eintrag ansehen',
    ])

    <p style="margin: 24px 0 8px; color: #3f3f46;">Das lohnt sich als Erstes:</p>
    <ul style="margin: 0 0 8px; padding-left: 20px; color: #3f3f46;">
        <li style="margin-bottom: 6px;">Beschreibung, Öffnungszeiten und Kontaktdaten prüfen</li>
        <li style="margin-bottom: 6px;">Logo und Fotos hochladen</li>
        <li style="margin-bottom: 6px;">Leistungen und Kategorien ergänzen, damit du besser gefunden wirst</li>
        <li>Deine Kunden um eine Bewertung bitten</li>
    </ul>
    <p style="margin: 0 0 8px; color: #3f3f46;">
        Alles das änderst du im
        <a href="{{ $dashboardUrl }}" style="color: {{ $brandColor }}; font-weight: 600;">Betriebsbereich</a>.
    </p>

    @if ($premiumSaleEnabled)
        <table cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 32px 0 0;">
            <tr>
                <td style="padding: 24px; background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 16px;">
                    <p style="margin: 0 0 8px; font-size: 12px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: {{ $brandColor }};">
                        Premium
                    </p>
                    <h2 style="margin: 0 0 12px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
                        Anfragen direkt in deinem Betriebsbereich
                    </h2>
                    <p style="margin: 0 0 16px; color: #3f3f46;">
                        Mit Premium gehen Kundenanfragen zu deinem Eintrag exklusiv an dich: Sie landen
                        sofort in deinem Betriebsbereich auf {{ $portalName }}, mit Namen, Telefonnummer
                        und E-Mail-Adresse — und du bekommst zu jeder Anfrage eine Mail. Kein Weiterverkauf
                        an mehrere Betriebe, kein Wettrennen um den ersten Rückruf.
                    </p>
                    <ul style="margin: 0 0 16px; padding-left: 20px; color: #3f3f46;">
                        <li style="margin-bottom: 6px;"><strong>Exklusive Anfragen</strong> samt Kontaktdaten, direkt im System</li>
                        <li style="margin-bottom: 6px;"><strong>Werbefreies Profil</strong> mit Fotogalerie und Leistungskatalog</li>
                        <li style="margin-bottom: 6px;"><strong>Verifiziert-Badge</strong> und Antworten auf Bewertungen</li>
                        <li><strong>Statistiken</strong> zu Aufrufen, Klicks und Anfragen</li>
                    </ul>
                    @if ($fromPrice)
                        <p style="margin: 0 0 4px; color: #18181b; font-weight: 600;">
                            Ab {{ $fromPrice }}, monatlich kündbar.
                        </p>
                    @endif
                    <p style="margin: 8px 0 0;">
                        <a href="{{ $premiumUrl }}" style="color: {{ $brandColor }}; font-weight: 600;">
                            Premium ansehen &rarr;
                        </a>
                    </p>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin: 32px 0 0; color: #3f3f46;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => $portalName]) }}
    </p>
@endsection
