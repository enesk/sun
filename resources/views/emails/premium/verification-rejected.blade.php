{{-- Nachweis abgelehnt (#11), Premium-Mail-Layout SUN-PREM-015 (#16). --}}
@extends('mail.premium.layout')

@section('preview')
    {{ __('Ihr Nachweis wurde nicht bestätigt') }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ __('Nachweis nicht bestätigt') }}
    </h1>
    <p style="margin: 0;">
        {{ $recipientName ? __('Hallo :name,', ['name' => $recipientName]) : __('Guten Tag,') }}
        <br>
        <br>
        {{ __('wir haben den eingereichten Nachweis für :company geprüft und können ihn leider nicht bestätigen. Grund:', ['company' => $companyName]) }}
    </p>

    <p style="margin: 16px 0 0; padding: 12px 16px; background-color: #f4f4f5; border-radius: 8px;">
        {!! nl2br(e($reason)) !!}
    </p>

    <p style="margin: 16px 0 0;">
        {{ __('Sie können jederzeit einen neuen Nachweis im Betriebsbereich hochladen.') }}
    </p>

    @include('mail.premium.partials.button', ['url' => $retryUrl, 'label' => __('Neuen Nachweis hochladen')])

    <p style="margin: 32px 0 0;">
        {{ __('Mit freundlichen Grüßen,') }}<br>
        {{ __('Ihr :app-Team', ['app' => \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName()]) }}
    </p>
@endsection
