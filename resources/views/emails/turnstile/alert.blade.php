{{--
    Alarm-Mail des Bot-Schutzes (#12). sun-Mail-Layout wie die Guide-Alarme,
    nur Inline-Styles, keine Assets. Nur die Überschrift in Warnrot, genau eine
    Schaltfläche. Die Mail rechnet nichts — alle Zahlen kommen aus dem Alarm.
--}}
@php
    $url = rescue(
        fn (): string => \App\Filament\Admin\Resources\TurnstileVerifications\TurnstileVerificationResource::getUrl('index', panel: 'admin'),
        fn (): string => url('/admin'),
        report: false,
    );
    $zelle = 'padding: 6px 8px; border-bottom: 1px solid #e4e4e7;';
@endphp

@extends('mail.sun.layout')

@section('preview')
    {{ $alert->tenantName }}: {{ $alert->headline }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #b91c1c;">
        {{ $alert->headline }}
    </h1>

    <p style="margin: 0 0 16px;">
        <strong>{{ $alert->tenantName }}:</strong> {{ $alert->message }}
    </p>

    <p style="margin: 0 0 16px;">
        <strong>Was jetzt passiert:</strong> {{ $alert->consequence }}
    </p>

    @if ($alert->numbers !== [])
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;" cellpadding="0" cellspacing="0" role="presentation">
            @foreach ($alert->numbers as $label => $wert)
                <tr>
                    <td style="{{ $zelle }} color: #71717a;">{{ $label }}</td>
                    <td style="{{ $zelle }} text-align: right; font-weight: 600; white-space: nowrap;">{{ $wert }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @include('mail.sun.partials.button', ['url' => $url, 'label' => 'Verifikations-Log ansehen'])

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Diese Nachricht kommt höchstens einmal je Stunde, Portal und Alarmtyp. Schwellen und Empfänger stehen in config/turnstile.php.
    </p>
@endsection
