{{-- Fuss aller sieben Mails an Betriebe (SUN-RQ #10).

     Drei Dinge, die in jeder der sieben stehen muessen:
     - Grussformel in Sie-Form (portal.mail.sign_off_name_formal),
     - die Statusadresse — signiert, ohne Ablauf, der Weg ohne Rueckfrage,
     - die Vorgangsnummer plus der Hinweis, dass eine Antwort auf diese Mail
       beim Vorgang landet (Betreffsmarke [REQ-id], Poller #5).

     Parameter: $profileRequest, $statusUrl, $portalName, $mailTenant.
     $withStatusLink = false, wenn die Mail schon einen Knopf dorthin hat. --}}
@php
    $footerBranding = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null);
    $withStatusLink = $withStatusLink ?? true;
@endphp

@if ($withStatusLink)
<p style="margin: 24px 0 0; font-size: 14px; color: #71717a;">
    <a href="{{ $statusUrl }}" style="color: {{ $footerBranding->brandColor() }};">Stand Ihrer Anfrage ansehen</a>
</p>
@endif

<p style="margin: 8px 0 0; font-size: 14px; color: #71717a;">
    Vorgangsnummer: REQ-{{ $profileRequest->id }}. Wenn Sie auf diese E-Mail antworten, landet Ihre
    Nachricht direkt bei diesem Vorgang.
</p>

<p style="margin: 32px 0 0; color: #3f3f46;">
    {{ __('portal.mail.sign_off') }}<br>
    {{ __('portal.mail.sign_off_name_formal', ['portal' => $portalName]) }}
</p>
