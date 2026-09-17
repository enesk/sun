{{-- Neuer Aenderungsvorschlag: Hinweis an den Portalbetreiber (App\Mail\EditSuggestionNotification). Texte stehen fest in der View, sun-Mail-Layout (SUN-PREM-015, #16). --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Neuer Änderungsvorschlag für {{ $suggestion->company->name ?? 'Unbekannt' }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Neuer Änderungsvorschlag
    </h1>
    <p style="margin: 0 0 24px;">
        Auf {{ $portalName }} wurde ein Änderungsvorschlag eingereicht. Prüf ihn in der Verwaltung:
    </p>

    <table style="width: 100%; margin-bottom: 24px;" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">Firma:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $suggestion->company->name ?? 'Unbekannt' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Bereich:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $suggestion->field_label }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Vorschlag:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $suggestion->suggested_value }}</td>
        </tr>
        @if($suggestion->reason)
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Begründung:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $suggestion->reason }}</td>
        </tr>
        @endif
        @if($suggestion->reporter_name)
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Name:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $suggestion->reporter_name }}</td>
        </tr>
        @endif
        @if($suggestion->reporter_email)
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">E-Mail:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $suggestion->reporter_email }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Tenant:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ tenant()?->name ?? tenant()?->id ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Zeitpunkt:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $suggestion->created_at->format('d.m.Y H:i') }} Uhr</td>
        </tr>
    </table>

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Automatische Nachricht von {{ $portalName }}.
    </p>
@endsection
