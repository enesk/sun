{{--
    Tagesbericht der Content-Pipeline (#22). Aufbau wie die uebrigen
    Systemmails des Projekts: Tabellenlayout, Inline-Styles, keine Assets.
--}}
@php
    $totals = $report['totals'];
    $cost = $report['cost'];
@endphp

<x-layouts.email>
    <x-slot name="preview">
        {{ __('Ratgeber-Tagesbericht :date: :published von :target Artikeln', [
            'date' => $report['date'],
            'published' => $totals['published'],
            'target' => $totals['target'],
        ]) }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                {{ __('Ratgeber-Tagesbericht') }} {{ $report['date'] }}
            </h1>

            <p style="margin: 0 0 16px; line-height: 24px">
                {{ __(':published von :target Artikeln veröffentlicht, :portals Portale, :failed offene Slots.', [
                    'published' => $totals['published'],
                    'target' => $totals['target'],
                    'portals' => $totals['portals'],
                    'failed' => $totals['failed_slots'],
                ]) }}
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b; width: 220px;">{{ __('Kosten heute') }}</td>
                    <td style="padding: 8px 0;">
                        {{ number_format($cost['today'], 2) }} USD
                        <span style="color: #64748b;">({{ (int) round($cost['today_share'] * 100) }} % {{ __('des Tagesbudgets') }})</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">{{ __('Kosten Monat') }}</td>
                    <td style="padding: 8px 0;">
                        {{ number_format($cost['month'], 2) }} USD
                        <span style="color: #64748b;">({{ (int) round($cost['month_share'] * 100) }} % {{ __('des Monatsbudgets') }})</span>
                    </td>
                </tr>
            </table>

            @if ($report['alerts'] !== [])
                <h2 style="margin: 32px 0 12px; font-size: 18px; font-weight: 600; color: #000">{{ __('Offene Alarme') }}</h2>

                @foreach ($report['alerts'] as $alert)
                    <p style="margin: 0 0 8px; line-height: 22px; color: {{ $alert['level'] === 'critical' ? '#b91c1c' : '#b45309' }}">
                        {{ $alert['message'] }}
                        @if ($alert['occurrences'] > 1)
                            <span style="color: #64748b;">({{ $alert['occurrences'] }}×, {{ __('seit') }} {{ $alert['since'] }})</span>
                        @endif
                    </p>
                @endforeach
            @endif

            <h2 style="margin: 32px 0 12px; font-size: 18px; font-weight: 600; color: #000">{{ __('Portale') }}</h2>

            @if (count($report['portals']) === 0)
                <p style="margin: 0; line-height: 22px; color: #64748b;">
                    {{ __('Kein Portal ist für die Ratgeber-Produktion freigeschaltet.') }}
                </p>
            @endif

            @foreach ($report['portals'] as $portal)
                <div style="border-top: 1px solid #e2e8f0; padding: 16px 0;">
                    <p style="margin: 0 0 8px; font-weight: 600; color: #0f172a">
                        {{ $portal['name'] }}
                        <span style="font-weight: 400; color: #64748b;">
                            {{ $portal['published'] }}/{{ $portal['target'] }} {{ __('veröffentlicht') }} ·
                            {{ number_format($portal['cost'], 2) }} USD
                        </span>
                    </p>

                    @foreach ($portal['articles'] as $article)
                        <p style="margin: 0 0 4px; line-height: 22px;">
                            {{ $article['at'] }} —
                            @if ($article['url'] !== '')
                                <a href="{{ $article['url'] }}" style="color: #2563eb;">{{ $article['title'] }}</a>
                            @else
                                {{ $article['title'] }}
                            @endif
                            <span style="color: #64748b;">
                                {{ $article['score'] !== null ? __('Score :score', ['score' => $article['score']]) : __('ohne Score') }},
                                {{ number_format($article['cost'], 2) }} USD
                            </span>
                        </p>
                    @endforeach

                    @foreach ($portal['failed_slots'] as $slot)
                        <p style="margin: 0 0 4px; line-height: 22px; color: #b91c1c;">
                            {{ $slot['title'] }}: {{ $slot['reason'] }}
                        </p>
                    @endforeach

                    @if ($portal['articles'] === [] && $portal['failed_slots'] === [])
                        <p style="margin: 0; line-height: 22px; color: #64748b;">{{ __('Heute ist nichts erschienen.') }}</p>
                    @endif
                </div>
            @endforeach

            <h2 style="margin: 32px 0 12px; font-size: 18px; font-weight: 600; color: #000">{{ __('Quellen und Provider') }}</h2>

            @forelse ($report['providers'] as $provider)
                <p style="margin: 0 0 4px; line-height: 22px;">
                    {{ $provider['provider'] }}: {{ $provider['status'] }}
                    <span style="color: #64748b;">({{ $provider['requests_today'] }} {{ __('Abrufe heute') }})</span>
                    @if ($provider['last_error'])
                        <span style="color: #b91c1c;">— {{ $provider['last_error'] }}</span>
                    @endif
                </p>
            @empty
                <p style="margin: 0; line-height: 22px; color: #64748b;">{{ __('Noch kein Provider-Zustand erfasst.') }}</p>
            @endforelse

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>
            <p style="padding-top: 12px; padding-bottom: 12px;">
                {{ __('Mit freundlichen Grüßen,') }}<br>
                {{ __('Ihr :app-System', ['app' => config('app.name')]) }}
            </p>
        </td>
    </tr>
</x-layouts.email>
