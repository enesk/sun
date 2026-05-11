@extends('layouts.dashboard')

@section('title', 'Statistiken')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Statistiken</h1>
            <p class="dash-page-subtitle">Wie Kunden Ihren Eintrag finden und nutzen.</p>
        </div>

        {{-- Perioden-Auswahl --}}
        <div class="flex items-center gap-1 mt-3 sm:mt-0" role="group" aria-label="Zeitraum auswählen">
            @foreach(['7d' => '7 Tage', '30d' => '30 Tage', '90d' => '90 Tage', '12m' => '12 Monate'] as $key => $label)
                <a href="{{ route('portal.owner.stats', ['period' => $key]) }}"
                   class="dash-period-btn {{ $period === $key ? 'dash-period-btn-active' : '' }}"
                   @if($period === $key) aria-current="true" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ============================================================
         KPI CARDS — Immer sichtbar (Free + Premium)
         ============================================================ --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
        {{-- Profilaufrufe --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span class="dash-stat-label">Aufrufe</span>
            </div>
            <span class="dash-stat-value">{{ number_format($summary['page_views']) }}</span>
            @include('partials.dashboard.stat-trend', ['change' => $summary['page_views_change']])
        </div>

        {{-- Kontaktklicks --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <span class="dash-stat-label">Kontakte</span>
            </div>
            <span class="dash-stat-value">{{ number_format($summary['contact_clicks']) }}</span>
            @include('partials.dashboard.stat-trend', ['change' => $summary['contact_clicks_change']])
        </div>

        {{-- Suchimpressionen --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <span class="dash-stat-label">Suchanfragen</span>
            </div>
            <span class="dash-stat-value">{{ number_format($summary['search_impressions']) }}</span>
            @include('partials.dashboard.stat-trend', ['change' => $summary['search_impressions_change']])
        </div>

        {{-- Rating --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-portal-accent" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                <span class="dash-stat-label">Rating</span>
            </div>
            <span class="dash-stat-value">{{ $company->rating > 0 ? number_format($company->rating, 1) : '—' }}</span>
            <span class="dash-stat-sub">{{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }}</span>
        </div>
    </div>

    @if($company->is_premium)
        {{-- ============================================================
             PREMIUM: Vollständige Statistiken
             ============================================================ --}}

        {{-- Trend-Chart --}}
        <div class="dash-card dash-card-padded mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold" style="color: var(--dash-text-primary)">Profilaufrufe — {{ ['7d' => 'Letzte 7 Tage', '30d' => 'Letzte 30 Tage', '90d' => 'Letzte 90 Tage', '12m' => 'Letzte 12 Monate'][$period] }}</h2>
                @if($summary['page_views_change'] !== null)
                    @include('partials.dashboard.stat-trend-badge', ['change' => $summary['page_views_change']])
                @endif
            </div>

            @if($trend->sum('page_views') > 0)
                <div class="dash-chart-bars" role="img" aria-label="Profilaufrufe Trend-Diagramm"
                     x-data="{ tooltip: null }"
                     @mouseleave="tooltip = null"
                     @click.outside="tooltip = null">
                    @php $maxVal = max($trend->max('page_views'), 1); @endphp
                    @foreach($trend as $i => $day)
                        <div class="dash-chart-bar-wrap"
                             @mouseenter="tooltip = { index: {{ $i }}, date: '{{ \Carbon\Carbon::parse($day['date'])->format('d.m.') }}', value: {{ $day['page_views'] }} }"
                             @touchstart.passive="tooltip = { index: {{ $i }}, date: '{{ \Carbon\Carbon::parse($day['date'])->format('d.m.') }}', value: {{ $day['page_views'] }} }">
                            <div class="dash-chart-bar"
                                 style="height: {{ max(($day['page_views'] / $maxVal) * 100, 2) }}%;"
                                 :class="{ 'dash-chart-bar-active': tooltip?.index === {{ $i }} }"></div>
                        </div>
                    @endforeach
                </div>

                {{-- Tooltip --}}
                <div x-show="tooltip" x-cloak
                     class="text-center mt-2 text-xs" style="color: var(--dash-text-secondary)">
                    <span x-text="tooltip?.date"></span>:
                    <strong x-text="tooltip?.value" style="color: var(--dash-text-primary)"></strong> Aufrufe
                </div>

                {{-- X-Achsen-Labels --}}
                <div class="flex justify-between mt-2">
                    <span class="text-xs" style="color: var(--dash-text-muted)">{{ \Carbon\Carbon::parse($trend->first()['date'])->format('d.m.') }}</span>
                    <span class="text-xs" style="color: var(--dash-text-muted)">{{ \Carbon\Carbon::parse($trend->last()['date'])->format('d.m.') }}</span>
                </div>
            @else
                <div class="dash-empty" style="padding: 2rem;">
                    <p class="dash-empty-description">Noch keine Aufrufe in diesem Zeitraum.</p>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Kontakt-Aufschlüsselung --}}
            <div class="dash-card dash-card-padded">
                <h2 class="text-sm font-semibold mb-4" style="color: var(--dash-text-primary)">Kontakt-Aufschlüsselung</h2>

                @php
                    $breakdown = $summary['contact_breakdown'];
                    $totalClicks = max($summary['contact_clicks'], 1);
                    $contactTypes = [
                        ['key' => 'phone', 'label' => 'Telefon', 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', 'color' => 'var(--dash-success)'],
                        ['key' => 'email', 'label' => 'E-Mail', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'var(--dash-info)'],
                        ['key' => 'website', 'label' => 'Website', 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9', 'color' => 'var(--portal-primary)'],
                        ['key' => 'map', 'label' => 'Karte', 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z', 'color' => 'var(--dash-warning)'],
                    ];
                @endphp

                @if($summary['contact_clicks'] > 0)
                    <div class="space-y-3">
                        @foreach($contactTypes as $type)
                            @php $val = $breakdown[$type['key']]; @endphp
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 shrink-0" style="color: {{ $type['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $type['icon'] }}"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm" style="color: var(--dash-text-secondary)">{{ $type['label'] }}</span>
                                        <span class="text-sm font-medium" style="color: var(--dash-text-primary)">{{ $val }}</span>
                                    </div>
                                    <div class="dash-progress" style="height: 6px;">
                                        <div class="dash-progress-fill" style="width: {{ round(($val / $totalClicks) * 100) }}%; background-color: {{ $type['color'] }};"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dash-empty" style="padding: 1.5rem;">
                        <p class="dash-empty-description">Noch keine Kontaktklicks in diesem Zeitraum.</p>
                    </div>
                @endif
            </div>

            {{-- Wochen-Trend --}}
            <div class="dash-card dash-card-padded">
                <h2 class="text-sm font-semibold mb-4" style="color: var(--dash-text-primary)">Wochen-Trend (letzte 8 Wochen)</h2>

                @if($weekly->isNotEmpty() && $weekly->sum('page_views') > 0)
                    @php $maxWeek = max($weekly->max('page_views'), 1); @endphp
                    <div class="space-y-2">
                        @foreach($weekly->take(8) as $week)
                            @php
                                $weekStart = \Carbon\Carbon::parse($week['week_start']);
                                $pct = round(($week['page_views'] / $maxWeek) * 100);
                            @endphp
                            <div class="flex items-center gap-3">
                                <span class="text-xs w-16 shrink-0" style="color: var(--dash-text-muted)">{{ $weekStart->format('d.m.') }}</span>
                                <div class="flex-1">
                                    <div class="dash-progress" style="height: 8px;">
                                        <div class="dash-progress-fill" style="width: {{ $pct }}%;"></div>
                                    </div>
                                </div>
                                <span class="text-xs font-medium w-12 text-right" style="color: var(--dash-text-primary)">{{ $week['page_views'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dash-empty" style="padding: 1.5rem;">
                        <p class="dash-empty-description">Noch keine Daten für den Wochen-Trend.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Top Suchbegriffe --}}
            <div class="dash-card dash-card-padded">
                <h2 class="text-sm font-semibold mb-4" style="color: var(--dash-text-primary)">Top Suchbegriffe</h2>

                @if($searchQueries->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($searchQueries as $i => $q)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="text-xs w-5 text-center shrink-0 font-medium" style="color: var(--dash-text-muted)">{{ $i + 1 }}</span>
                                    <span class="text-sm truncate" style="color: var(--dash-text-secondary)">{{ $q['query'] }}</span>
                                </div>
                                <span class="text-sm font-medium shrink-0 ml-3" style="color: var(--dash-text-primary)">{{ $q['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dash-empty" style="padding: 1.5rem;">
                        <p class="dash-empty-description">Noch keine Suchdaten vorhanden.</p>
                    </div>
                @endif
            </div>

            {{-- Top Referrer --}}
            <div class="dash-card dash-card-padded">
                <h2 class="text-sm font-semibold mb-4" style="color: var(--dash-text-primary)">Besucherherkunft</h2>

                @if($referrers->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($referrers as $i => $ref)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="text-xs w-5 text-center shrink-0 font-medium" style="color: var(--dash-text-muted)">{{ $i + 1 }}</span>
                                    <span class="text-sm truncate" style="color: var(--dash-text-secondary)">{{ $ref['domain'] }}</span>
                                </div>
                                <span class="text-sm font-medium shrink-0 ml-3" style="color: var(--dash-text-primary)">{{ $ref['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dash-empty" style="padding: 1.5rem;">
                        <p class="dash-empty-description">Noch keine Referrer-Daten vorhanden.</p>
                    </div>
                @endif
            </div>
        </div>

    @else
        {{-- ============================================================
             FREE USER: Basis-KPIs oben (bereits angezeigt) +
             Soft-Lock auf detaillierte Statistiken
             ============================================================ --}}

        {{-- Geblurrte Premium-Statistiken mit Lock-Overlay --}}
        <div class="dash-card relative overflow-hidden" style="min-height: 320px;">
            {{-- Geblurrter Mock-Content --}}
            <div class="pointer-events-none select-none p-6" style="filter: blur(5px); opacity: 0.5;" aria-hidden="true">
                {{-- Mock Trend-Titel --}}
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm font-semibold" style="color: var(--dash-text-primary)">Profilaufrufe — Letzte 30 Tage</span>
                    <span class="text-xs px-2 py-0.5 rounded" style="background-color: rgba(22, 163, 74, 0.1); color: #16a34a;">+24%</span>
                </div>

                {{-- Mock Chart (CSS bars) --}}
                <div class="flex items-end gap-1.5" style="height: 120px;">
                    @foreach([35, 42, 28, 55, 48, 62, 45, 70, 58, 75, 52, 80, 65, 88, 72, 90, 68, 85, 78, 92, 70, 95, 82, 98, 75, 88, 90, 105, 95, 110] as $val)
                        <div class="flex-1 rounded-t" style="height: {{ ($val / 110) * 100 }}%; background-color: var(--portal-primary); opacity: 0.6;"></div>
                    @endforeach
                </div>

                {{-- Mock Stats Grid --}}
                <div class="grid grid-cols-3 gap-4 mt-6">
                    <div>
                        <div class="text-lg font-bold" style="color: var(--dash-text-primary)">1.247</div>
                        <div class="text-xs" style="color: var(--dash-text-muted)">Profilaufrufe</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold" style="color: var(--dash-text-primary)">89</div>
                        <div class="text-xs" style="color: var(--dash-text-muted)">Kontaktklicks</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold" style="color: var(--dash-text-primary)">342</div>
                        <div class="text-xs" style="color: var(--dash-text-muted)">Suchanfragen</div>
                    </div>
                </div>

                {{-- Mock Herkunft --}}
                <div class="mt-6">
                    <span class="text-sm font-semibold" style="color: var(--dash-text-primary)">Top Suchbegriffe</span>
                    <div class="space-y-2 mt-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--dash-text-secondary)">maler berlin</span>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary)">124</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--dash-text-secondary)">malermeister kreuzberg</span>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary)">87</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--dash-text-secondary)">wohnung streichen</span>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary)">53</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lock-Overlay --}}
            <div class="absolute inset-0 flex items-center justify-center" style="background: linear-gradient(180deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.85) 40%, rgba(255,255,255,0.95) 100%);">
                <div class="text-center px-6 max-w-sm">
                    <div class="w-14 h-14 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                        <svg class="w-7 h-7" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold mb-1" style="color: var(--dash-text-primary)">Detaillierte Statistiken</h3>
                    <p class="text-sm mb-4" style="color: var(--dash-text-secondary)">
                        Sehen Sie Wochen-Trends, Besucherherkunft und welche Suchbegriffe Kunden zu Ihnen führen.
                    </p>
                    <a href="{{ route('portal.owner.premium') }}"
                       class="dash-btn dash-btn-sm inline-flex items-center gap-1.5"
                       style="background-color: var(--portal-accent); color: white;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                        Premium freischalten — 9,90 €/Monat
                    </a>
                    <p class="text-xs mt-2" style="color: var(--dash-text-muted)">Keine Bindung. Jederzeit kündbar.</p>
                </div>
            </div>
        </div>
    @endif
@endsection
