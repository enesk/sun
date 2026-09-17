{{--
    Monatsreport an Pro/Premium-Betriebe (#16). Mailable: App\Mail\Company\MonthlyStatsReportMail.
    Texte: portal.owner.statistics.report.mail.* und portal.owner.statistics.metrics.*
--}}
@extends('mail.sun.layout')

@php
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');
    $t = fn (string $key, array $replace = []) => __("portal.owner.statistics.report.mail.{$key}", $replace);
    $portalName = \App\Support\Tenancy\TenantMailBranding::for($mailTenant ?? null)->portalName();
@endphp

@section('preview')
    {{ $t('preview', ['monat' => $monthLabel]) }}
@endsection

@section('content')
    <h1 style="margin: 0 0 16px; font-size: 24px; line-height: 32px; font-weight: 700; color: #18181b;">{{ $t('heading') }}</h1>
    <p style="margin: 0 0 24px;">
        {{ $recipientName ? $t('greeting', ['name' => $recipientName]) : $t('greeting_anonymous') }}<br>
        {{ $t('intro', ['firma' => $companyName, 'monat' => $monthLabel]) }}
    </p>

    <table cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <th align="left" style="padding: 0 0 8px; font-size: 14px; font-weight: 500; color: #71717a;">&nbsp;</th>
            <th align="right" style="padding: 0 0 8px; font-size: 14px; font-weight: 500; color: #71717a;">{{ $t('column_month', ['monat' => $monthLabel]) }}</th>
            <th align="right" style="padding: 0 0 8px 12px; font-size: 14px; font-weight: 500; color: #71717a;">{{ $t('column_change') }}</th>
        </tr>
        @foreach($totals as $metric => $value)
            @php $change = $changes[$metric] ?? null; @endphp
            <tr>
                <td style="padding: 12px 0; border-top: 1px solid #e4e4e7; color: #3f3f46;">{{ __("portal.owner.statistics.metrics.{$metric}") }}</td>
                <td align="right" style="padding: 12px 0; border-top: 1px solid #e4e4e7; font-size: 18px; font-weight: 700; color: #18181b;">{{ $number($value) }}</td>
                <td align="right" style="padding: 12px 0 12px 12px; border-top: 1px solid #e4e4e7; font-size: 14px; color: #71717a; white-space: nowrap;">
                    @if($change === null)
                        {{ $t('no_change') }}
                    @else
                        {{ ($change > 0 ? '+' : '').number_format($change, 0, ',', '.') }}&nbsp;%
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    @if($ranking)
        <p style="margin: 24px 0 0;">{{ $t('ranking', ['stadt' => $ranking['city'], 'platz' => $ranking['position'], 'anzahl' => $ranking['total']]) }}</p>
    @endif

    @include('mail.sun.partials.button', ['url' => $dashboardUrl, 'label' => $t('cta')])

    <p style="margin: 32px 0 0;">
        {{ __('Viele Grüße') }}<br>
        {{ __('dein Team von :portal', ['portal' => $portalName]) }}
    </p>
@endsection

@section('footer')
    {{ $t('unsubscribe') }} <a href="{{ $settingsUrl }}" style="color: #71717a;">{{ $t('unsubscribe_link') }}</a>
@endsection
