{{--
    Tagesbericht der Content-Pipeline (#22) an den Portalbetreiber.
    sun-Mail-Layout (SUN-PREM-015, #16), nur Inline-Styles, keine Assets.
--}}
@php
    $totals = $report['totals'];
    $cost = $report['cost'];
@endphp

@extends('mail.sun.layout')

@section('preview')
    Ratgeber-Tagesbericht {{ $report['date'] }}: {{ $totals['published'] }} von {{ $totals['target'] }} Artikeln
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Ratgeber-Tagesbericht {{ $report['date'] }}
    </h1>

    <p style="margin: 0 0 16px;">
        {{ $totals['published'] }} von {{ $totals['target'] }} Artikeln veröffentlicht, {{ $totals['portals'] }} Portale, {{ $totals['failed_slots'] }} offene Slots.
    </p>

    @if (($totals['refreshed'] ?? 0) > 0)
        {{-- Aktualisierungen sind kein Artikel des Tages (#103) und
             stehen deshalb in einem eigenen Satz, nicht in der Zahl. --}}
        <p style="margin: 0 0 16px;">
            {{ trans_choice('{1}Zusätzlich wurde ein Artikel aktualisiert.|[2,*]Zusätzlich wurden :count Artikel aktualisiert.', $totals['refreshed'], ['count' => $totals['refreshed']]) }}
        </p>
    @endif

    <table style="width: 100%; margin-bottom: 8px;" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="width: 35%; padding: 4px 0; vertical-align: top; color: #71717a;">Kosten heute:</td>
            <td style="padding: 4px 0; vertical-align: top;">
                {{ number_format($cost['today'], 2) }} USD
                <span style="color: #71717a;">({{ (int) round($cost['today_share'] * 100) }} % des Tagesbudgets)</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 0; vertical-align: top; color: #71717a;">Kosten Monat:</td>
            <td style="padding: 4px 0; vertical-align: top;">
                {{ number_format($cost['month'], 2) }} USD
                <span style="color: #71717a;">({{ (int) round($cost['month_share'] * 100) }} % des Monatsbudgets)</span>
            </td>
        </tr>
    </table>

    @if ($report['alerts'] !== [])
        <h2 style="margin: 32px 0 12px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">Offene Alarme</h2>

        @foreach ($report['alerts'] as $alert)
            <p style="margin: 0 0 8px; color: {{ $alert['level'] === 'critical' ? '#b91c1c' : '#b45309' }};">
                {{ $alert['message'] }}
                @if ($alert['occurrences'] > 1)
                    <span style="color: #71717a;">({{ $alert['occurrences'] }}×, seit {{ $alert['since'] }})</span>
                @endif
            </p>
        @endforeach
    @endif

    <h2 style="margin: 32px 0 12px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">Portale</h2>

    @if (count($report['portals']) === 0)
        <p style="margin: 0; color: #71717a;">
            Kein Portal ist für die Ratgeber-Produktion freigeschaltet.
        </p>
    @endif

    @foreach ($report['portals'] as $portal)
        @php
            $refreshes = $portal['refreshes'] ?? [];
            $refreshed = $portal['refreshed'] ?? count($refreshes);
        @endphp

        <div style="border-top: 1px solid #e4e4e7; padding: 16px 0;">
            <p style="margin: 0 0 8px; font-weight: 600; color: #18181b;">
                {{ $portal['name'] }}
                <span style="font-weight: 400; color: #71717a;">
                    {{ $portal['published'] }}/{{ $portal['target'] }} veröffentlicht ·
                    @if ($refreshed > 0)
                        {{ $refreshed }} aktualisiert ·
                    @endif
                    {{ number_format($portal['cost'], 2) }} USD
                </span>
            </p>

            @foreach ($portal['articles'] as $article)
                <p style="margin: 0 0 4px;">
                    {{ $article['at'] }} —
                    @if ($article['url'] !== '')
                        <a href="{{ $article['url'] }}" style="color: #2563eb;">{{ $article['title'] }}</a>
                    @else
                        {{ $article['title'] }}
                    @endif
                    <span style="color: #71717a;">
                        {{ $article['score'] !== null ? 'Score '.$article['score'] : 'ohne Score' }},
                        {{ number_format($article['cost'], 2) }} USD
                    </span>
                </p>
            @endforeach

            @if ($refreshes !== [])
                @if ($portal['articles'] === [])
                    {{-- Feststellung, keine Bewertung: die Bewertung
                         leistet die Zahl 0/:target in der Portalzeile. --}}
                    <p style="margin: 0 0 4px; color: #71717a;">
                        Heute ist kein neuer Artikel erschienen.
                    </p>
                @endif

                <p style="margin: 12px 0 4px; font-size: 13px; font-weight: 600; color: #71717a;">
                    Aktualisiert
                </p>

                @foreach ($refreshes as $refresh)
                    <p style="margin: 0 0 4px;">
                        {{ $refresh['at'] }} —
                        @if ($refresh['url'] !== '')
                            <a href="{{ $refresh['url'] }}" style="color: #2563eb;">{{ $refresh['title'] }}</a>
                        @else
                            {{ $refresh['title'] }}
                        @endif
                        <span style="color: #71717a;">
                            erschienen am {{ $refresh['first_published'] }},
                            {{ number_format($refresh['cost'], 2) }} USD
                        </span>
                    </p>
                @endforeach
            @endif

            @foreach ($portal['failed_slots'] as $slot)
                <p style="margin: 0 0 4px; color: #b91c1c;">
                    {{ $slot['title'] }}: {{ $slot['reason'] }}
                </p>
            @endforeach

            @if ($portal['articles'] === [] && $refreshes === [] && $portal['failed_slots'] === [])
                <p style="margin: 0; color: #71717a;">Heute ist nichts erschienen.</p>
            @endif
        </div>
    @endforeach

    <h2 style="margin: 32px 0 12px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">Quellen und Provider</h2>

    @forelse ($report['providers'] as $provider)
        <p style="margin: 0 0 4px;">
            {{ $provider['provider'] }}: {{ $provider['status'] }}
            <span style="color: #71717a;">({{ $provider['requests_today'] }} Abrufe heute)</span>
            @if ($provider['last_error'])
                <span style="color: #b91c1c;">— {{ $provider['last_error'] }}</span>
            @endif
        </p>
    @empty
        <p style="margin: 0; color: #71717a;">Noch kein Provider-Zustand erfasst.</p>
    @endforelse

    <p style="margin: 32px 0 0; font-size: 14px; color: #71717a;">
        Automatische Nachricht der Ratgeber-Pipeline. Details findest du in der Content-Übersicht.
    </p>
@endsection
