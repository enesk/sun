{{-- Statusseite des Magic-Links (App\Http\Controllers\RequestVerificationController, SUN-RQ #6).
     Sie-Form, mobile first, ab 360 px geprueft.

     Bewusst eigenstaendig und ohne Theme-Layout: die Seite wird auf jeder
     Portal-Domain ausgeliefert, laeuft aber ohne Tenant-Kontext und darf
     deshalb an keinem Portal-Asset und keinem Vite-Bundle haengen. Die
     ausgebaute Fassung im Portal-Design bringt Ticket #10.
     Stile inline, damit die Seite auch ohne gebautes CSS stimmt. --}}
@php
    $kopf = [
        'verified' => 'Vielen Dank — bestätigt',
        'cancelled' => 'Anfrage abgelehnt',
        'expired' => 'Link abgelaufen',
        'already' => 'Schon bestätigt',
        'closed' => 'Vorgang abgeschlossen',
        'invalid' => 'Link nicht gültig',
    ][$state] ?? 'Anfrage';

    $text = [
        'verified' => 'Wir haben Ihre Bestätigung erhalten und bearbeiten die Anfrage. Sie müssen nichts weiter tun — sobald sie erledigt ist, schreiben wir Ihnen.',
        'cancelled' => 'Wir haben Ihren Widerspruch notiert. An Ihrem Eintrag wird nichts geändert und nichts gelöscht.',
        'expired' => 'Dieser Bestätigungslink ist abgelaufen. Aus Sicherheitsgründen gilt er nur wenige Tage. Sie können sich einen neuen Link an die in Ihrem Eintrag hinterlegte E-Mail-Adresse schicken lassen.',
        'already' => 'Diese Anfrage ist bereits bestätigt. Ein zweiter Klick ändert nichts.',
        'closed' => 'Dieser Vorgang ist abgeschlossen. Falls Sie noch etwas ändern möchten, stellen Sie bitte eine neue Anfrage auf Ihrer Profilseite.',
        'invalid' => 'Dieser Link gehört zu keinem offenen Vorgang. Möglicherweise wurde er beim Kopieren aus der E-Mail abgeschnitten oder durch einen neueren Link ersetzt.',
    ][$state] ?? '';

    $farbe = $brandColor ?: '#18181b';
@endphp
    <!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $kopf }} — {{ $portalName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#3f3f46; line-height:1.55;">
<div style="max-width:560px; margin:0 auto; padding:24px 16px 48px;">

    <p style="margin:0 0 20px; font-size:14px;">
        <a href="{{ $portalUrl }}" style="color:{{ $farbe }}; text-decoration:none; font-weight:600;">{{ $portalName }}</a>
    </p>

    <div style="background-color:#ffffff; border-radius:16px; padding:24px 20px; box-shadow:0 1px 2px rgba(0,0,0,.06);">
        <h1 style="margin:0 0 12px; font-size:22px; line-height:1.3; font-weight:700; color:#18181b;">
            {{ $kopf }}
        </h1>

        <p style="margin:0 0 16px;">{{ $text }}</p>

        @if (session('status'))
            <p style="margin:0 0 16px; padding:12px 14px; border-radius:10px; background-color:#f0fdf4; color:#166534;">
                {{ session('status') }}
            </p>
        @endif

        @if (session('error'))
            <p style="margin:0 0 16px; padding:12px 14px; border-radius:10px; background-color:#fef2f2; color:#991b1b;">
                {{ session('error') }}
            </p>
        @endif

        @if ($state === 'expired' && $resendUrl !== null && ! session('status'))
            <form method="POST" action="{{ $resendUrl }}" style="margin:0 0 16px;">
                @csrf
                <button type="submit"
                        style="display:inline-block; width:100%; max-width:320px; padding:14px 20px; border:0; border-radius:12px; background-color:{{ $farbe }}; color:#ffffff; font-size:16px; font-weight:600; cursor:pointer;">
                    Neuen Link anfordern
                </button>
            </form>
        @endif

        @if ($profileRequest !== null)
            @if (($statusUrl ?? null) !== null)
                <p style="margin:0 0 8px;">
                    <a href="{{ $statusUrl }}" style="color:{{ $farbe }};">Stand der Anfrage ansehen</a>
                </p>
            @endif
            <p style="margin:0; font-size:14px; color:#71717a;">
                Vorgangsnummer: REQ-{{ $profileRequest->id }}
            </p>
        @endif
    </div>

    <p style="margin:20px 0 0; font-size:14px; color:#71717a;">
        Fragen zu diesem Vorgang? Antworten Sie einfach auf unsere E-Mail — Ihre Nachricht landet
        direkt beim richtigen Vorgang.
    </p>
</div>
</body>
</html>
