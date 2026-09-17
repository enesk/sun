{{--
    Einladung in ein Team (App\Mail\Tenant\UserInvitation).
    Empfaenger: eingeladene E-Mail-Adresse. Texte fest im View, Tenantname aus der Einladung.
    Layout: mail.sun.layout; der Tenant der Einladung geht als $mailTenant mit.
--}}
@extends('mail.sun.layout')

@php
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    Du wurdest eingeladen, {{ $invitation->tenant->name }} beizutreten
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Einladung zu {{ $invitation->tenant->name }}
    </h1>
    <p style="margin: 0 0 16px;">
        Hallo,
    </p>
    <p style="margin: 0;">
        du wurdest eingeladen, dem Team von {{ $invitation->tenant->name }} beizutreten. Nimm die Einladung mit einem Klick an.
    </p>

    @include('mail.sun.partials.button', ['url' => route('invitations'), 'label' => 'Einladung annehmen'])

    <p style="margin: 32px 0 16px;">
        Du kennst die Einladung nicht? Dann kannst du diese E-Mail einfach ignorieren.
    </p>
    <p style="margin: 0;">
        Viele Grüße<br>
        Dein Team von {{ $portalName }}
    </p>
@endsection
