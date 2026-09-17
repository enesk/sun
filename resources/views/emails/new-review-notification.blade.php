{{-- Neue Bewertung: Hinweis an den Portalbetreiber (App\Mail\NewReviewNotification). Texte stehen fest in der View, sun-Mail-Layout (SUN-PREM-015, #16). --}}
@extends('mail.sun.layout')

@php
    // Portalname aus dem Tenant der Mail, nie aus config('app.name').
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Neue Bewertung für {{ $review->company->name ?? 'Unbekannt' }} ({{ $review->rating }} Sterne)
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Neue Bewertung
    </h1>
    <p style="margin: 0 0 24px;">
        Auf {{ $portalName }} ist eine neue Bewertung eingegangen. Hier die Details:
    </p>

    <table style="width: 100%; margin-bottom: 24px;" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">Firma:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $review->company->name ?? 'Unbekannt' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Bewertung:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $review->rating }} / 5 Sterne</td>
        </tr>
        @if($review->author_name)
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Verfasser:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $review->author_name }}</td>
        </tr>
        @endif
        @if($review->title)
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Titel:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $review->title }}</td>
        </tr>
        @endif
        @if($review->body)
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Text:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $review->body }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Tenant:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ tenant()?->name ?? tenant()?->id ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Zeitpunkt:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $review->created_at->format('d.m.Y H:i') }} Uhr</td>
        </tr>
    </table>

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Automatische Nachricht von {{ $portalName }}.
    </p>
@endsection
