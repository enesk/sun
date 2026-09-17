{{-- Nachweis abgelehnt (#11), Premium-Mail-Layout SUN-PREM-015 (#16). --}}
@extends('mail.sun.layout')

@php
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    {{ __('Dein Nachweis wurde nicht bestätigt') }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ __('Nachweis nicht bestätigt') }}
    </h1>
    <p style="margin: 0 0 16px;">
        {{ $recipientName ? __('portal.mail.greeting', ['name' => $recipientName]) : __('portal.mail.greeting_anonymous') }}
    </p>
    <p style="margin: 0;">
        {{ __('wir haben den Nachweis für :company geprüft und können ihn leider nicht bestätigen. Der Grund:', ['company' => $companyName]) }}
    </p>

    <p style="margin: 16px 0 0; padding: 12px 16px; background-color: #f4f4f5; border-radius: 8px;">
        {!! nl2br(e($reason)) !!}
    </p>

    <p style="margin: 16px 0 0;">
        {{ __('Du kannst jederzeit einen neuen Nachweis in deinem Betriebsbereich hochladen.') }}
    </p>

    @include('mail.sun.partials.button', ['url' => $retryUrl, 'label' => __('Neuen Nachweis hochladen')])

    <p style="margin: 32px 0 0;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => $portalName]) }}
    </p>
@endsection
