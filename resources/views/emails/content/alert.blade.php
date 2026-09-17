{{--
    Sofortmeldung eines kritischen Alarms der Content-Pipeline (#22) an den
    Portalbetreiber. sun-Mail-Layout (SUN-PREM-015, #16), nur Inline-Styles.
--}}
@extends('mail.sun.layout')

@section('preview')
    Ratgeber-Alarm: {{ $alert->message }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ratgeber-Alarm
    </h1>

    <p style="margin: 0 0 24px; color: #b91c1c;">
        {{ $alert->message }}
    </p>

    <table style="width: 100%; margin-bottom: 8px;" cellpadding="0" cellspacing="0" role="presentation">
        @if ($portal !== null)
            <tr>
                <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">Portal:</td>
                <td style="padding: 4px 0; vertical-align: top;">{{ $portal }}</td>
            </tr>
        @endif
        <tr>
            <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">Ursache:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $alert->key }}</td>
        </tr>
        @if ($alert->for_date)
            <tr>
                <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Tag:</td>
                <td style="padding: 4px 0; vertical-align: top;">{{ $alert->for_date }}</td>
            </tr>
        @endif
        @if ($alert->slot !== null)
            <tr>
                <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Slot:</td>
                <td style="padding: 4px 0; vertical-align: top;">{{ $alert->slot }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Vorkommen:</td>
            <td style="padding: 4px 0; vertical-align: top;">{{ $alert->occurrences }}</td>
        </tr>
    </table>

    <p style="margin: 24px 0 0;">
        Du findest den Alarm im Störungsband der Übersicht und im Tagesbericht um 20:00 Uhr, bis er sich auflöst.
    </p>

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Automatische Nachricht der Ratgeber-Pipeline.
    </p>
@endsection
