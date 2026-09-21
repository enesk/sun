{{--
    Tagesbericht des Ratgebersystems (#13) an die Inhaber des Content-Panels.
    sun-Mail-Layout, nur Inline-Styles, keine Assets.
--}}
@use('App\Guide\Support\CheckedQuote')
@php
    $totals = $report['totals'];
    // "Unverändert" steht im Satz oben und im Panel; die Spalte weicht der
    // Quote, damit die Tabelle unter 600 px nicht quer scrollt (§11.2).
    $columns = [
        'updated' => 'Aktualisiert',
        'created' => 'Neu',
        'review' => 'Prüfung',
        'failed' => 'Fehlgeschlagen',
        'deferred' => 'Verschoben',
    ];
    $cell = 'padding: 6px 8px; border-bottom: 1px solid #e4e4e7; text-align: right; white-space: nowrap;';
    $totalPercent = CheckedQuote::percent((int) ($totals['checked_due'] ?? 0), (int) ($totals['due'] ?? 0));
@endphp

@extends('mail.sun.layout')

@section('preview')
    Ratgeber-Tageslauf {{ \Illuminate\Support\Carbon::parse($report['date'])->format('d.m.') }}: {{ $totalPercent !== null ? $totalPercent.' % der fälligen Themen geprüft' : 'keine fälligen Themen' }}, {{ $totals['failed'] }} fehlgeschlagen
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ratgeber-Tageslauf {{ $report['date'] }}
    </h1>

    <p style="margin: 0 0 16px; font-size: 16px; font-weight: 700;">
        Geprüft: {{ (int) ($totals['checked_due'] ?? 0) }} von {{ (int) ($totals['due'] ?? 0) }} fälligen Themen ({{ CheckedQuote::label($totalPercent) }})@if (CheckedQuote::belowTarget($totalPercent))<span style="color: #b45309;"> — &#9888; unter Ziel {{ CheckedQuote::TARGET_PERCENT }} %</span>@endif
    </p>

    <p style="margin: 0 0 16px;">
        {{ $totals['checked'] }} Themen in {{ $totals['portals'] }} Portalen geprüft:
        {{ $totals['created'] }} neu, {{ $totals['updated'] }} aktualisiert, {{ $totals['unchanged'] }} unverändert,
        {{ $totals['review'] }} in der Prüfung, {{ $totals['failed'] }} fehlgeschlagen, {{ $totals['deferred'] }} verschoben.
        @if ($totals['open'] > 0)
            {{ $totals['open'] }} Läufe sind noch unterwegs.
        @endif
    </p>

    <p style="margin: 0 0 16px;">
        Kosten heute: {{ number_format((float) $totals['cost_usd'], 2) }} USD
        @if ($report['budget']['share'] !== null)
            <span style="color: #71717a;">({{ (int) round($report['budget']['share'] * 100) }} % des Tagesbudgets von {{ number_format((float) $report['budget']['daily_usd_total'], 2) }} USD)</span>
        @endif
    </p>

    @if ($report['alerts'] !== [])
        <h2 style="margin: 32px 0 12px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">Alarme</h2>

        @foreach ($report['alerts'] as $alert)
            <p style="margin: 0 0 8px; color: {{ $alert['level'] === 'critical' ? '#b91c1c' : ($alert['level'] === 'warning' ? '#b45309' : '#3f3f46') }};">
                @if ($alert['tenant'] !== null)
                    <strong>{{ $alert['tenant'] }}:</strong>
                @endif
                {{ $alert['message'] }}
                @if ($alert['occurrences'] > 1)
                    <span style="color: #71717a;">({{ $alert['occurrences'] }}×)</span>
                @endif
            </p>
        @endforeach
    @endif

    <h2 style="margin: 32px 0 12px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">Portale</h2>

    @if ($report['portals'] === [])
        <p style="margin: 0; color: #71717a;">Kein Portal ist für das Ratgebersystem freigeschaltet.</p>
    @else
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <th style="{{ $cell }} text-align: left; color: #71717a; font-weight: 600;">Portal</th>
                <th style="{{ $cell }} color: #71717a; font-weight: 600;">Geprüft</th>
                <th style="{{ $cell }} color: #71717a; font-weight: 600;">Quote</th>
                @foreach ($columns as $label)
                    <th style="{{ $cell }} color: #71717a; font-weight: 600;">{{ $label }}</th>
                @endforeach
                <th style="{{ $cell }} color: #71717a; font-weight: 600;">USD</th>
            </tr>
            @foreach ($report['portals'] as $portal)
                <tr>
                    <td style="{{ $cell }} text-align: left; white-space: normal;">
                        {{ $portal['name'] }}
                        @isset($portal['error'])
                            <span style="color: #b91c1c;">— {{ $portal['error'] }}</span>
                        @endisset
                    </td>
                    @php($percent = CheckedQuote::percent((int) ($portal['checked_due'] ?? 0), (int) ($portal['due'] ?? 0)))
                    <td style="{{ $cell }}">{{ (int) ($portal['checked_due'] ?? 0) }} / {{ (int) ($portal['due'] ?? 0) }}</td>
                    <td style="{{ $cell }} {{ $percent === null ? 'color: #71717a;' : (CheckedQuote::belowTarget($percent) ? 'color: #b45309;' : '') }}">
                        @if (CheckedQuote::belowTarget($percent))
                            &#9888; {{ CheckedQuote::label($percent) }}<br><span style="font-size: 12px;">unter Ziel {{ CheckedQuote::TARGET_PERCENT }} %</span>
                        @else
                            {{ CheckedQuote::label($percent) }}
                        @endif
                    </td>
                    @foreach (array_keys($columns) as $key)
                        <td style="{{ $cell }} {{ $key === 'failed' && $portal[$key] > 0 ? 'color: #b91c1c;' : '' }}">{{ $portal[$key] }}</td>
                    @endforeach
                    <td style="{{ $cell }}">{{ number_format((float) $portal['cost_usd'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Automatische Nachricht des Ratgebersystems. Details findest du im Content-Panel unter „Tageslauf“.
    </p>
@endsection
