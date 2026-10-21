{{-- Tagesdigest der Anfragen-Inbox, 08:00 an die Administratoren
     (App\ProfileRequests\Mail\DailyDigestMail, SUN-RQ #9).

     Interne Mail, deshalb Du-Form und kein Portal-Branding: die Zeilen
     stammen aus allen Portalen, das Portal steht je Zeile. Jede Zeile
     verlinkt in das Central-Panel — der Digest ist eine Arbeitsliste, keine
     Statistik.

     Parameter: $tag (Stichtag), $gruppen, $autoDisabled. --}}
@extends('mail.sun.layout')

@php
    $bloecke = [
        'erledigt' => ['Automatisch erledigt', 'Hat die Automatik gestern ohne dich übernommen.'],
        'freigabe' => ['Wartet auf deine Freigabe', 'Bestätigt, aber keine Auto-Regel zuständig.'],
        'verifikation' => ['Wartet auf Bestätigung', 'Der Betrieb hat noch nicht bestätigt.'],
        'abgelaufen' => ['Abgelaufen oder storniert', 'Gestern geschlossen, ohne Ergebnis.'],
    ];
@endphp

@section('preview')
    {{ count($gruppen['erledigt'] ?? []) }} automatisch erledigt, {{ count($gruppen['freigabe'] ?? []) }} warten auf dich
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Anfragen-Inbox am {{ $tag->format('d.m.Y') }}
    </h1>

    @if ($autoDisabled)
        <p style="margin: 0 0 16px; padding: 12px 16px; background-color: #fef2f2; border-radius: 8px; color: #991b1b;">
            <strong>Die Auto-Regeln sind abgeschaltet</strong> (REQUESTS_AUTO_DISABLED). Es wird nichts
            automatisch übernommen und keine Löschung ausgeführt — auch keine, deren Karenzfrist
            abgelaufen ist.
        </p>
    @endif

    @foreach ($bloecke as $key => [$titel, $untertitel])
        @php($zeilen = $gruppen[$key] ?? [])
        @continue($zeilen === [])

        <h2 style="margin: 24px 0 4px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">
            {{ $titel }} ({{ count($zeilen) }})
        </h2>
        <p style="margin: 0 0 12px; font-size: 14px; color: #71717a;">{{ $untertitel }}</p>

        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 8px;">
            @foreach ($zeilen as $zeile)
                <tr>
                    <td style="padding: 8px 0; border-top: 1px solid #e4e4e7;">
                        <a href="{{ $zeile['url'] }}" style="color: #18181b; font-weight: 600; text-decoration: none;">
                            REQ-{{ $zeile['id'] }} · {{ $zeile['titel'] }}
                        </a>
                        <span style="display: block; font-size: 14px; color: #52525b;">{{ $zeile['zeile'] }}</span>
                    </td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Diese Mail geht nur raus, wenn es etwas zu melden gibt. Eine automatisch übernommene
        Änderung lässt sich im Panel mit „Rückgängig“ zurückdrehen.
    </p>
@endsection
