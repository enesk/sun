{{-- Freie Antwort aus dem Panel (App\ProfileRequests\Mail\RequestReplyMail, #7).
     Der gesamte Text kommt aus dem Panel; hier steht nur der Rahmen.
     nl2br auf e() — der Text ist Eingabe aus dem Panel und wird nicht als HTML
     ausgeliefert, nur seine Zeilenumbrueche bleiben erhalten. --}}
@extends('mail.sun.layout')

@php
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $portalName = $branding->portalName();
@endphp

@section('preview')
    Nachricht zu Ihrer Anfrage
@endsection

@section('content')
    <div style="margin: 0 0 24px; color: #3f3f46;">
        {!! nl2br(e($nachricht)) !!}
    </div>

    <p style="margin: 32px 0 0; color: #3f3f46;">
        {{ __('portal.mail.sign_off') }}<br>
        {{ __('portal.mail.sign_off_name', ['portal' => $portalName]) }}
    </p>

    <p style="margin: 24px 0 0; font-size: 12px; color: #a1a1aa;">
        Vorgangsnummer REQ-{{ $profileRequest->id }} — bitte im Betreff stehen lassen,
        dann landet Ihre Antwort direkt bei diesem Vorgang.
    </p>
@endsection
