{{-- Anfrage abgelehnt, mit Grund (App\ProfileRequests\Mail\RequestRejectedMail, SUN-RQ #10).
     Sie-Form, sun-Mail-Layout, Branding aus dem Tenant der Mail.

     Der Grund steht als eigener, abgesetzter Block — er ist der Inhalt
     dieser Mail, nicht ein Nachsatz. --}}
@extends('mail.sun.layout')

@php
    $branding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
@endphp

@section('preview')
    Zu Ihrer Anfrage REQ-{{ $profileRequest->id }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Wir können Ihre Anfrage nicht umsetzen
    </h1>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        @if ($profileRequest->requester_name)
            Guten Tag {{ $profileRequest->requester_name }},
        @else
            Guten Tag,
        @endif
    </p>

    <p style="margin: 0 0 16px; color: #3f3f46;">
        wir haben Ihre Anfrage zum Eintrag
        <strong>{{ $companyName !== '' ? $companyName : 'Ihres Betriebs' }}</strong> auf
        {{ $portalName }} geprüft und setzen sie nicht um. Am Eintrag hat sich dadurch nichts
        geändert.
    </p>

    @if ($reason !== '')
        <table cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 0 0 24px;">
            <tr>
                <td style="padding: 16px 18px; background-color: #fafafa; border-left: 3px solid {{ $branding->brandColor() }}; border-radius: 0 8px 8px 0;">
                    <p style="margin: 0 0 4px; font-size: 14px; color: #71717a;">Grund</p>
                    <p style="margin: 0; color: #3f3f46; white-space: pre-line;">{{ $reason }}</p>
                </td>
            </tr>
        </table>
    @endif

    <h2 style="margin: 0 0 8px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
        Wenn Sie das anders sehen
    </h2>
    <p style="margin: 0 0 16px; color: #3f3f46;">
        Antworten Sie einfach auf diese E-Mail und schreiben Sie dazu, was aus Ihrer Sicht fehlt.
        Ihre Nachricht landet direkt bei diesem Vorgang, Sie müssen nichts erneut ausfüllen. Wenn Sie
        den Nachweis haben, dass Sie für den Betrieb sprechen (zum Beispiel eine E-Mail von der
        Firmendomain), hängen Sie ihn gern an.
    </p>

    @include('emails.profile-requests.partials.footer')
@endsection
