{{--
    E-Mail-Bestaetigung auf einem Portal (App\Mail\User\VerifyEmail im Tenant-Kontext, #11).
    Empfaenger: Person, die sich gerade eingetragen/registriert hat.
    Alle Texte aus portal.mail.*; ohne Tenant gilt emails.user.verify-email.
    Layout: mail.sun.layout; der Tenant geht als $mailTenant mit.
--}}
@extends('mail.sun.layout')

@section('preview')
    {{ __('portal.mail.verify_email.preview') }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        {{ __('portal.mail.verify_email.heading') }}
    </h1>
    <p style="margin: 0 0 16px;">
        {{ filled($name) ? __('portal.mail.greeting', ['name' => $name]) : __('portal.mail.greeting_anonymous') }}
    </p>
    <p style="margin: 0;">
        {{ __('portal.mail.verify_email.intro') }}
    </p>

    @include('mail.sun.partials.button', ['url' => $url, 'label' => __('portal.mail.verify_email.button')])

    <p style="margin: 32px 0 16px;">
        {{ __('portal.mail.verify_email.outro') }}
    </p>
    <p style="margin: 0;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name') }}
    </p>

    <div role="separator" style="background-color: #e4e4e7; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>

    <p style="margin: 0; font-size: 14px; line-height: 20px; color: #71717a;">
        {{ __('portal.mail.verify_email.link_hint') }} <a href="{{ $url }}" style="color: #71717a;">{{ $url }}</a>
    </p>
@endsection
