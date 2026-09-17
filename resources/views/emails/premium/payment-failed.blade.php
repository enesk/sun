{{-- Zahlung fehlgeschlagen (#5), Premium-Mail-Layout SUN-PREM-015 (#16). Der Tenant kommt als $mailTenant, weil der Versand aus dem Central-Kontext laeuft. --}}
@extends('mail.sun.layout')

@php
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
    $supportEmail = (string) (($mailTenant ?? null)?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('app.support_email'));
@endphp

@section('preview')
    {{ __('Zahlung fehlgeschlagen – bitte prüf deine Zahlungsdaten') }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ __('Zahlung fehlgeschlagen') }}
    </h1>
    <p style="margin: 0 0 16px;">
        {{ filled($subscription->user?->name) ? __('portal.mail.greeting', ['name' => $subscription->user->name]) : __('portal.mail.greeting_anonymous') }}
    </p>
    <p style="margin: 0;">
        {{ __('die Zahlung für dein :plan-Paket konnte nicht eingezogen werden. Deine Vorteile bleiben noch :days Tage aktiv. Prüf bis dahin bitte deine Zahlungsdaten, sonst fällt dein Profil auf den Basis-Eintrag zurück. Deine Inhalte bleiben dabei erhalten.', ['plan' => $subscription->plan?->name, 'days' => $graceDays]) }}
    </p>

    @include('mail.sun.partials.button', ['url' => $billingUrl, 'label' => __('Zahlungsdaten prüfen')])

    @if($supportEmail !== '')
        <p style="margin: 32px 0 0; font-size: 14px; line-height: 20px; color: #71717a;">
            {{ __('Etwas stimmt nicht? Schreib uns einfach:') }}
            <a href="mailto:{{ $supportEmail }}" style="color: #71717a;">{{ $supportEmail }}</a>
        </p>
    @endif

    <p style="margin: 32px 0 0;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => $portalName]) }}
    </p>
@endsection
