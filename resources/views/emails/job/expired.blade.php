{{--
    Stellenanzeige abgelaufen (App\Mail\Job\JobExpired).
    Empfaenger: Betrieb, dessen Anzeige nach 30 Tagen ausgelaufen ist. Texte fest im View.
    Layout: mail.sun.layout; der Tenant geht als $mailTenant mit, der Versand laeuft aus dem Central-Kontext.
--}}
@extends('mail.sun.layout')

@section('preview')
    Deine Stellenanzeige „{{ $job->title }}" ist abgelaufen
@endsection

@section('content')
    @php
        $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
        $tenantDomain = $tenant->domains?->first()?->domain;
        $dashboardUrl = $tenantDomain
            ? 'https://' . $tenantDomain . '/firmenprofil/stellenanzeigen'
            : url('/firmenprofil/stellenanzeigen');
        $supportEmail = (string) ($tenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('app.support_email'));
    @endphp

    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Stellenanzeige abgelaufen
    </h1>
    <p style="margin: 0 0 16px;">
        Hallo,
    </p>
    <p style="margin: 0;">
        deine Stellenanzeige <strong>„{{ $job->title }}"</strong> für <strong>{{ $company->name }}</strong> ist nach 30 Tagen automatisch abgelaufen und damit nicht mehr öffentlich sichtbar.
    </p>

    <div style="margin: 24px 0; padding: 16px; background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 12px;">
        <p style="margin: 0 0 8px; font-weight: 600; color: #18181b;">{{ $job->title }}</p>
        <p style="margin: 0; font-size: 14px; line-height: 20px; color: #71717a;">
            {{ $job->employment_type_label }}
            @if($job->location_display) · {{ $job->location_display }} @endif
            <br>
            Veröffentlicht: {{ $job->published_at->format('d.m.Y') }} · Abgelaufen: {{ $job->expires_at->format('d.m.Y') }}
        </p>
    </div>

    <p style="margin: 0;">
        Du kannst die Anzeige jederzeit in deinem Betriebsbereich erneut veröffentlichen – sie läuft dann wieder 30 Tage.
    </p>

    @include('mail.sun.partials.button', ['url' => $dashboardUrl, 'label' => 'Stellenanzeigen verwalten'])

    <div role="separator" style="background-color: #e4e4e7; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>

    @if($supportEmail !== '')
        <p style="margin: 0 0 24px; font-size: 14px; line-height: 20px; color: #71717a;">
            Du hast Fragen? Schreib uns einfach:
            <a href="mailto:{{ $supportEmail }}" style="color: #71717a;">{{ $supportEmail }}</a>
        </p>
    @endif

    <p style="margin: 0;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>
@endsection
