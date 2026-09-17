{{-- Claim-Antrag abgelehnt: Nachricht an den Antragsteller (App\Mail\ClaimRejected). Texte stehen fest in der View, sun-Mail-Layout. --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
    $company = $claimRequest->company;
    $companyName = $company->name ?? '';
    $reason = trim((string) ($claimRequest->rejection_reason ?? ''));
@endphp

@section('preview')
    Dein Antrag für {{ $companyName }} konnte nicht freigegeben werden
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Dein Antrag konnte nicht freigegeben werden
    </h1>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Hallo {{ $claimRequest->user->name ?? '' }},
    </p>
    <p style="margin: 0 0 24px; color: #3f3f46;">
        wir haben deinen Antrag für <strong>{{ $companyName }}</strong> auf {{ $portalName }} geprüft.
        Leider konnten wir den Eintrag nicht für dich freischalten.
    </p>

    @if ($reason !== '')
        <table style="width: 100%; margin: 0 0 24px; background: #f4f4f5; border-radius: 8px;" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td style="padding: 16px 20px;">
                    <p style="margin: 0 0 4px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; color: #71717a;">Grund</p>
                    <p style="margin: 0; color: #3f3f46;">{{ $reason }}</p>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin: 0 0 8px; color: #3f3f46;">So geht es weiter:</p>
    <ul style="margin: 0 0 24px; padding-left: 20px; color: #3f3f46;">
        <li style="margin-bottom: 6px;">Stell den Antrag erneut und lade einen gut lesbaren Nachweis hoch (z.&nbsp;B. Gewerbeschein oder Handelsregisterauszug)</li>
        <li style="margin-bottom: 6px;">Achte darauf, dass Name und Anschrift im Nachweis zum Eintrag passen</li>
        <li>Passt etwas am Eintrag selbst nicht, kannst du uns eine Änderung vorschlagen</li>
    </ul>

    @if ($company)
        @include('mail.sun.partials.button', [
            'url' => $branding->url('/'.$company->url_slug),
            'label' => 'Zum Eintrag',
        ])
    @endif

    <p style="margin: 24px 0 0; color: #3f3f46;">
        Du hast Fragen dazu? Antworte einfach auf diese Mail.
    </p>

    <p style="margin: 32px 0 0; color: #3f3f46;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => $portalName]) }}
    </p>
@endsection
