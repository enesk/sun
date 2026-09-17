{{-- Zahlung fehlgeschlagen (#5), Premium-Mail-Layout SUN-PREM-015 (#16). Der Tenant kommt als $mailTenant, weil der Versand aus dem Central-Kontext laeuft. --}}
@extends('mail.premium.layout')

@section('preview')
    {{ __('Zahlung fehlgeschlagen – bitte Zahlungsdaten prüfen') }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ __('Zahlung fehlgeschlagen') }}
    </h1>
    <p style="margin: 0;">
        {{ __('Hallo :name,', ['name' => $subscription->user?->name]) }}
        <br>
        <br>
        {{ __('die Zahlung für Ihr :plan-Paket konnte nicht eingezogen werden. Ihre Vorteile bleiben noch :days Tage aktiv. Bitte prüfen Sie bis dahin Ihre Zahlungsdaten, sonst wird Ihr Profil auf den Basis-Eintrag zurückgestuft. Ihre Inhalte bleiben dabei erhalten.', ['plan' => $subscription->plan?->name, 'days' => $graceDays]) }}
    </p>

    @include('mail.premium.partials.button', ['url' => $billingUrl, 'label' => __('Zahlungsdaten prüfen')])

    <p style="margin: 32px 0 0;">
        {{ __('Mit freundlichen Grüßen,') }}<br>
        {{ __('Ihr :app-Team', ['app' => \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName()]) }}
    </p>
@endsection
