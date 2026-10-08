{{--
    Tagesbericht des Bot-Schutzes (#12) an die Betreiber. sun-Mail-Layout, nur
    Inline-Styles. Dieselben Zahlen wie `php artisan turnstile:report`; die
    Vorlage rechnet nichts ausser den Prozentangaben.
--}}
@php
    $summen = $report['totals'];
    $url = rescue(
        fn (): string => \App\Filament\Admin\Resources\TurnstileVerifications\TurnstileVerificationResource::getUrl('index', panel: 'admin'),
        fn (): string => url('/admin'),
        report: false,
    );
    $zelle = 'padding: 6px 8px; border-bottom: 1px solid #e4e4e7; text-align: right; white-space: nowrap;';
    $quote = static fn (float $anteil): string => number_format($anteil * 100, 1, ',', '.').' %';
@endphp

@extends('mail.sun.layout')

@section('preview')
    Bot-Schutz {{ $report['date_label'] }}: {{ $summen['registrations'] }} Registrierungen, {{ $summen['listings'] }} Einträge, {{ $summen['blocked'] }} blockiert
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">
        Bot-Schutz {{ $report['date_label'] }}
    </h1>

    <p style="margin: 0 0 16px;">
        {{ $summen['registrations'] }} Registrierungen und {{ $summen['listings'] }} Firmeneinträge in {{ $summen['portals'] }} Portalen.
        {{ $summen['checks'] }} Prüfungen, davon {{ $summen['blocked'] }} abgewiesen ({{ $quote((float) $summen['blocked_share']) }})
        und {{ $summen['errors'] }} mit Fehler ({{ $quote((float) $summen['error_share']) }}).
    </p>

    @if ($summen['quarantined_accounts'] + $summen['quarantined_listings'] > 0)
        <p style="margin: 0 0 16px;">
            Neu in Quarantäne: {{ $summen['quarantined_accounts'] }} Konten, {{ $summen['quarantined_listings'] }} Einträge.
        </p>
    @endif

    @if ($summen['errors'] > 0)
        <p style="margin: 0 0 16px; color: #b45309;">
            &#9888; Fehler heißt: Siteverify war nicht erreichbar. Bei fail_mode=open sind diese Anfragen ohne Turnstile durchgelaufen.
        </p>
    @endif

    <h2 style="margin: 32px 0 12px; font-size: 18px; line-height: 26px; font-weight: 700; color: #18181b;">Portale</h2>

    @if ($report['portals'] === [])
        <p style="margin: 0; color: #71717a;">Kein Portal mit lesbarem Verifikations-Log.</p>
    @else
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <th style="{{ $zelle }} text-align: left; color: #71717a; font-weight: 600;">Portal</th>
                <th style="{{ $zelle }} color: #71717a; font-weight: 600;">Registr.</th>
                <th style="{{ $zelle }} color: #71717a; font-weight: 600;">Einträge</th>
                <th style="{{ $zelle }} color: #71717a; font-weight: 600;">Blockiert</th>
                <th style="{{ $zelle }} color: #71717a; font-weight: 600;">Fehler</th>
                <th style="{{ $zelle }} color: #71717a; font-weight: 600;">Quarant.</th>
            </tr>
            @foreach ($report['portals'] as $portal)
                <tr>
                    <td style="{{ $zelle }} text-align: left;">{{ $portal['name'] }}</td>
                    <td style="{{ $zelle }}">{{ $portal['registrations'] }}</td>
                    <td style="{{ $zelle }}">{{ $portal['listings'] }}</td>
                    <td style="{{ $zelle }} {{ $portal['blocked'] > 0 ? 'color: #b91c1c;' : '' }}">{{ $portal['blocked'] }}@if ($portal['checks'] > 0)<span style="color: #71717a;"> ({{ $quote((float) $portal['blocked_share']) }})</span>@endif</td>
                    <td style="{{ $zelle }} {{ $portal['errors'] > 0 ? 'color: #b45309;' : '' }}">{{ $portal['errors'] }}</td>
                    <td style="{{ $zelle }}">{{ $portal['quarantined_accounts'] }} / {{ $portal['quarantined_listings'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="{{ $zelle }} text-align: left; font-weight: 700;">Summe</td>
                <td style="{{ $zelle }} font-weight: 700;">{{ $summen['registrations'] }}</td>
                <td style="{{ $zelle }} font-weight: 700;">{{ $summen['listings'] }}</td>
                <td style="{{ $zelle }} font-weight: 700;">{{ $summen['blocked'] }}</td>
                <td style="{{ $zelle }} font-weight: 700;">{{ $summen['errors'] }}</td>
                <td style="{{ $zelle }} font-weight: 700;">{{ $summen['quarantined_accounts'] }} / {{ $summen['quarantined_listings'] }}</td>
            </tr>
        </table>
        <p style="margin: 8px 0 0; font-size: 12px; color: #71717a;">
            Quarantäne: Konten / Einträge, die an diesem Tag markiert wurden. Spalte „Registr.“ zählt angelegte Konten, „Blockiert“ abgewiesene Prüfungen.
        </p>
    @endif

    @if ($report['unreadable'] !== [])
        <p style="margin: 16px 0 0; font-size: 14px; color: #b45309;">
            Ohne lesbares Log: {{ implode(', ', $report['unreadable']) }}
        </p>
    @endif

    @include('mail.sun.partials.button', ['url' => $url, 'label' => 'Verifikations-Log ansehen'])
@endsection
